<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../backend/conexion.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login_admin.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrfValido(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token']);
}

function estadosPermitidos(): array
{
    return [
        'Recibido',
        'En Revisión',
        'Esperando Repuestos',
        'Reparando',
        'Listo para entregar',
        'Entregado',
    ];
}

function guardarImagenEvidencia(array $archivo): ?string
{
    if (!isset($archivo['error']) || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo cargar la imagen.');
    }

    $permitidos = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($archivo['tmp_name']);
    if (!isset($permitidos[$mime])) {
        throw new RuntimeException('Formato de imagen no permitido.');
    }

    $extension = $permitidos[$mime];
    $nombre = 'evidencia_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $carpetaDestino = __DIR__ . '/../backend/uploads/';
    $rutaAbsoluta = $carpetaDestino . $nombre;

    if (!is_dir($carpetaDestino) && !mkdir($carpetaDestino, 0775, true) && !is_dir($carpetaDestino)) {
        throw new RuntimeException('No se pudo crear la carpeta de uploads.');
    }

    if (!move_uploaded_file($archivo['tmp_name'], $rutaAbsoluta)) {
        throw new RuntimeException('No se pudo guardar la imagen.');
    }

    return '../backend/uploads/' . $nombre;
}

function obtenerDetallesPresupuesto(PDO $pdo, int $mantenimientoId): array
{
    $stmt = $pdo->prepare('
        SELECT id, concepto, costo
        FROM detalles_presupuesto
        WHERE mantenimiento_id = :mantenimiento_id
        ORDER BY id ASC
    ');
    $stmt->execute(['mantenimiento_id' => $mantenimientoId]);
    return $stmt->fetchAll();
}

function registrarEmailSalida(
    PDO $pdo,
    ?int $mantenimientoId,
    string $destinatario,
    string $asunto,
    string $cuerpo,
    string $estado,
    ?string $mensajeError = null
): void {
    $stmt = $pdo->prepare('
        INSERT INTO emails_salida (
            mantenimiento_id, destinatario, asunto, cuerpo, estado_envio, mensaje_error, enviado_en
        ) VALUES (
            :mantenimiento_id, :destinatario, :asunto, :cuerpo, :estado_envio, :mensaje_error, :enviado_en
        )
    ');
    $stmt->execute([
        'mantenimiento_id' => $mantenimientoId,
        'destinatario' => $destinatario,
        'asunto' => $asunto,
        'cuerpo' => $cuerpo,
        'estado_envio' => $estado,
        'mensaje_error' => $mensajeError,
        'enviado_en' => $estado === 'enviado' ? date('Y-m-d H:i:s') : null,
    ]);
}

function enviarCorreoServicio(PDO $pdo, ?int $mantenimientoId, string $correoDestino, string $asunto, string $contenido): bool
{
    if (!filter_var($correoDestino, FILTER_VALIDATE_EMAIL)) {
        registrarEmailSalida($pdo, $mantenimientoId, $correoDestino, $asunto, $contenido, 'fallido', 'Correo invalido');
        return false;
    }

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/plain; charset=UTF-8\r\n";
    $headers .= "From: Pc Salud <no-reply@pcsalud.local>\r\n";

    $enviado = @mail($correoDestino, $asunto, $contenido, $headers);
    registrarEmailSalida(
        $pdo,
        $mantenimientoId,
        $correoDestino,
        $asunto,
        $contenido,
        $enviado ? 'enviado' : 'fallido',
        $enviado ? null : 'mail() retorno false'
    );

    return $enviado;
}

function generarTokenUnico(PDO $pdo, int $longitud = 10): string
{
    $caracteres = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $max = strlen($caracteres) - 1;

    do {
        $token = '';
        for ($i = 0; $i < $longitud; $i++) {
            $token .= $caracteres[random_int(0, $max)];
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM mantenimientos WHERE token = :token');
        $stmt->execute(['token' => $token]);
        $existe = (int)$stmt->fetchColumn() > 0;
    } while ($existe);

    return $token;
}

$seccion = $_GET['seccion'] ?? 'inicio';
$accion = $_GET['accion'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$mensaje = '';
$error = '';
$tokenGenerado = '';

if ($seccion === 'logout') {
    session_destroy();
    header('Location: login_admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido()) {
        $error = 'Sesion invalida. Recarga la pagina e intenta nuevamente.';
    }

    if ($error === '' && $seccion === 'crear') {
        $marca = trim($_POST['marca'] ?? '');
        $tipo = trim($_POST['tipo_equipo'] ?? '');
        $razon = trim($_POST['razon_servicio'] ?? '');
        $fechaIngreso = trim($_POST['fecha_ingreso'] ?? '');
        $nombreCliente = trim($_POST['nombre_cliente'] ?? '');
        $emailCliente = trim($_POST['email_cliente'] ?? '');
        $whatsappCliente = trim($_POST['whatsapp_cliente'] ?? '');

        if ($marca === '' || $tipo === '' || $razon === '' || $fechaIngreso === '' || $nombreCliente === '' || $emailCliente === '' || $whatsappCliente === '') {
            $error = 'Todos los campos son obligatorios.';
        } elseif (!in_array($tipo, ['Portatil', 'PC de Escritorio'], true)) {
            $error = 'Tipo de equipo invalido.';
        } elseif (!filter_var($emailCliente, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ingresa un correo valido.';
        } else {
            $token = generarTokenUnico($pdo);
            $fechaEntrega = date('Y-m-d', strtotime($fechaIngreso . ' +7 days'));

            $stmt = $pdo->prepare('
                INSERT INTO mantenimientos (
                    token, marca, tipo_equipo, razon_servicio, fecha_ingreso, fecha_entrega,
                    nombre_cliente, email_cliente, whatsapp_cliente, diagnostico_actual, estado, costo_estimado, estado_presupuesto, ruta_imagen
                ) VALUES (
                    :token, :marca, :tipo_equipo, :razon_servicio, :fecha_ingreso, :fecha_entrega,
                    :nombre_cliente, :email_cliente, :whatsapp_cliente, :diagnostico_actual, :estado, :costo_estimado, :estado_presupuesto, :ruta_imagen
                )
            ');
            $stmt->execute([
                'token' => $token,
                'marca' => $marca,
                'tipo_equipo' => $tipo,
                'razon_servicio' => $razon,
                'fecha_ingreso' => $fechaIngreso,
                'fecha_entrega' => $fechaEntrega,
                'nombre_cliente' => $nombreCliente,
                'email_cliente' => $emailCliente,
                'whatsapp_cliente' => $whatsappCliente,
                'diagnostico_actual' => '',
                'estado' => 'Recibido',
                'costo_estimado' => null,
                'estado_presupuesto' => 'Pendiente',
                'ruta_imagen' => null,
            ]);

            $mensaje = 'Mantenimiento creado correctamente.';
            $tokenGenerado = $token;
            $mantenimientoIdCreado = (int)$pdo->lastInsertId();

            $contenidoCorreo = "Hola {$nombreCliente},\n\n" .
                "Tu mantenimiento fue registrado en PC SALUD.\n" .
                "Token de seguimiento: {$token}\n" .
                "Marca: {$marca}\n" .
                "Fecha estimada de entrega: {$fechaEntrega}\n\n" .
                "Gracias por confiar en nosotros.";
            enviarCorreoServicio($pdo, $mantenimientoIdCreado, $emailCliente, 'Registro de servicio - PC SALUD', $contenidoCorreo);
        }
    }

    if ($error === '' && $seccion === 'pendientes' && $accion === 'editar' && $id > 0) {
        $diagnostico = trim($_POST['diagnostico_actual'] ?? '');
        $fechaEntrega = trim($_POST['fecha_entrega'] ?? '');
        $estado = trim($_POST['estado'] ?? '');
        $conceptos = $_POST['concepto'] ?? [];
        $costos = $_POST['costo'] ?? [];
        $rutaImagen = null;

        if ($fechaEntrega === '') {
            $error = 'La fecha de entrega no puede estar vacia.';
        } elseif (!in_array($estado, estadosPermitidos(), true)) {
            $error = 'Selecciona un estado valido.';
        } else {
            $detallesLimpios = [];
            $totalCosto = 0.00;

            if (is_array($conceptos) && is_array($costos)) {
                $max = max(count($conceptos), count($costos));
                for ($i = 0; $i < $max; $i++) {
                    $concepto = trim((string)($conceptos[$i] ?? ''));
                    $costoRaw = trim((string)($costos[$i] ?? ''));

                    if ($concepto === '' && $costoRaw === '') {
                        continue;
                    }
                    if ($concepto === '' || $costoRaw === '' || !is_numeric($costoRaw)) {
                        $error = 'Cada detalle debe tener concepto y costo numerico.';
                        break;
                    }

                    $costo = number_format((float)$costoRaw, 2, '.', '');
                    $detallesLimpios[] = [
                        'concepto' => $concepto,
                        'costo' => $costo,
                    ];
                    $totalCosto += (float)$costo;
                }
            }

            if ($error === '') {
                try {
                    $rutaImagen = guardarImagenEvidencia($_FILES['imagen_evidencia'] ?? []);
                } catch (RuntimeException $ex) {
                    $error = $ex->getMessage();
                }
            }

            if ($error === '') {
                $actual = obtenerMantenimientoPorId($pdo, $id);
                $rutaFinal = $rutaImagen ?? ($actual['ruta_imagen'] ?? null);
                $nuevoEstadoEsListo = $estado === 'Listo para entregar' && ($actual['estado'] ?? '') !== 'Listo para entregar';

                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare('
                        UPDATE mantenimientos
                        SET
                            diagnostico_actual = :diagnostico_actual,
                            fecha_entrega = :fecha_entrega,
                            estado = :estado,
                            costo_estimado = :costo_estimado,
                            ruta_imagen = :ruta_imagen
                        WHERE id = :id
                    ');
                    $stmt->execute([
                        'diagnostico_actual' => $diagnostico,
                        'fecha_entrega' => $fechaEntrega,
                        'estado' => $estado,
                        'costo_estimado' => !empty($detallesLimpios) ? number_format($totalCosto, 2, '.', '') : null,
                        'ruta_imagen' => $rutaFinal,
                        'id' => $id,
                    ]);

                    $del = $pdo->prepare('DELETE FROM detalles_presupuesto WHERE mantenimiento_id = :mantenimiento_id');
                    $del->execute(['mantenimiento_id' => $id]);

                    if (!empty($detallesLimpios)) {
                        $ins = $pdo->prepare('
                            INSERT INTO detalles_presupuesto (mantenimiento_id, concepto, costo)
                            VALUES (:mantenimiento_id, :concepto, :costo)
                        ');
                        foreach ($detallesLimpios as $detalleItem) {
                            $ins->execute([
                                'mantenimiento_id' => $id,
                                'concepto' => $detalleItem['concepto'],
                                'costo' => $detalleItem['costo'],
                            ]);
                        }
                    }

                    $pdo->commit();
                    $mensaje = 'Mantenimiento actualizado correctamente.';

                    if ($nuevoEstadoEsListo) {
                        $contenidoCorreo = "Hola {$actual['nombre_cliente']},\n\n" .
                            "Tu equipo ya esta LISTO PARA ENTREGAR en PC SALUD.\n" .
                            "Token: {$actual['token']}\n" .
                            "Marca: {$actual['marca']}\n" .
                            "Fecha de entrega: {$fechaEntrega}\n\n" .
                            "Te esperamos. Gracias por confiar en nosotros.";
                        enviarCorreoServicio(
                            $pdo,
                            (int)$id,
                            (string)($actual['email_cliente'] ?? ''),
                            'Equipo listo para entregar - PC SALUD',
                            $contenidoCorreo
                        );
                    }
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = 'Ocurrio un error al guardar el desglose de costos.';
                }
            }
        }
    }
    if ($error === '' && $seccion === 'pendientes' && $accion === 'eliminar' && $id > 0) {
        $stmt = $pdo->prepare('DELETE FROM mantenimientos WHERE id = :id');
        $stmt->execute(['id' => $id]);
        header('Location: panel_admin.php?seccion=pendientes&msg=eliminado');
        exit;
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'eliminado') {
    $mensaje = 'Registro eliminado correctamente.';
}

function obtenerMantenimientoPorId(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM mantenimientos WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin | Pc Salud</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="theme-switch">
        <span id="themeLabel">Modo claro</span>
        <input type="checkbox" id="themeToggle" aria-label="Cambiar tema">
    </div>
    <div class="admin-layout">
        <aside class="sidebar">
            <h2>Pc Salud</h2>
            <nav>
                <a href="panel_admin.php?seccion=crear">Crear nuevo mantenimiento</a>
                <a href="panel_admin.php?seccion=pendientes">Mantenimientos pendientes</a>
                <a href="historial_clientes.php">Historial por cliente</a>
                <a href="opiniones.php">Opiniones</a>
                <a href="cola_correos.php">Cola de correos</a>
                <a href="panel_admin.php?seccion=logout">Cerrar sesion</a>
            </nav>
        </aside>

        <main class="content">
            <?php if ($mensaje !== ''): ?>
                <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($seccion === 'inicio'): ?>
                <h1 class="page-title">Bienvenido a Pc Salud</h1>
                <p class="page-subtitle">Usa el menu lateral para gestionar mantenimientos.</p>
                <?php
                $equiposTaller = (int)$pdo->query("SELECT COUNT(*) FROM mantenimientos WHERE estado <> 'Entregado'")->fetchColumn();
                $ingresosTotales = (float)$pdo->query("SELECT COALESCE(SUM(costo_estimado),0) FROM mantenimientos WHERE estado_presupuesto = 'Aprobado'")->fetchColumn();
                $marcaFrecuenteStmt = $pdo->query("
                    SELECT marca, COUNT(*) AS total
                    FROM mantenimientos
                    GROUP BY marca
                    ORDER BY total DESC, marca ASC
                    LIMIT 1
                ");
                $marcaFrecuente = $marcaFrecuenteStmt->fetch();
                ?>
                <div class="dashboard-cards">
                    <article class="dash-card">
                        <h3>Equipos en Taller</h3>
                        <p><?= $equiposTaller ?></p>
                    </article>
                    <article class="dash-card">
                        <h3>Ingresos Totales</h3>
                        <p>$ <?= number_format($ingresosTotales, 2) ?></p>
                    </article>
                    <article class="dash-card">
                        <h3>Marca mas frecuente</h3>
                        <p><?= htmlspecialchars($marcaFrecuente['marca'] ?? 'Sin datos') ?></p>
                    </article>
                </div>
            <?php endif; ?>

            <?php if ($seccion === 'crear'): ?>
                <h1 class="page-title">Crear nuevo mantenimiento</h1>
                <p class="page-subtitle">Registra los datos iniciales del equipo.</p>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="field-group">
                        <label for="marca">Marca</label>
                        <input id="marca" name="marca" required>
                    </div>
                    <div class="field-group">
                        <label for="tipo_equipo">Tipo</label>
                        <select id="tipo_equipo" name="tipo_equipo" required>
                            <option value="">Seleccione</option>
                            <option value="Portatil">Portatil</option>
                            <option value="PC de Escritorio">PC de Escritorio</option>
                        </select>
                    </div>
                    <div class="field-group">
                        <label for="razon_servicio">Razon del servicio</label>
                        <textarea id="razon_servicio" name="razon_servicio" required></textarea>
                    </div>
                    <div class="field-group">
                        <label for="fecha_ingreso">Fecha que se dejo</label>
                        <input id="fecha_ingreso" type="date" name="fecha_ingreso" required>
                    </div>
                    <div class="field-group">
                        <label for="nombre_cliente">Nombre del cliente</label>
                        <input id="nombre_cliente" name="nombre_cliente" required>
                    </div>
                    <div class="field-group">
                        <label for="email_cliente">Email cliente</label>
                        <input id="email_cliente" type="email" name="email_cliente" placeholder="cliente@correo.com" required>
                    </div>
                    <div class="field-group">
                        <label for="whatsapp_cliente">WhatsApp cliente</label>
                        <input id="whatsapp_cliente" name="whatsapp_cliente" placeholder="Maria andrea-31455555" required>
                    </div>
                    <button class="btn btn-primary" type="submit">Guardar mantenimiento</button>
                </form>

                <?php if ($tokenGenerado !== ''): ?>
                    <div class="token-box">
                        <p><strong>Token generado para el cliente</strong></p>
                        <div class="token-value"><?= htmlspecialchars($tokenGenerado) ?></div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($seccion === 'pendientes' && $accion === ''): ?>
                <h1 class="page-title">Mantenimientos pendientes</h1>
                <p class="page-subtitle">Listado por fecha de ingreso ascendente.</p>

                <?php
                $stmt = $pdo->query('SELECT id, marca, tipo_equipo, estado FROM mantenimientos ORDER BY fecha_ingreso ASC, id ASC');
                $items = $stmt->fetchAll();
                ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Marca</th>
                                <th>Tipo de PC</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$items): ?>
                                <tr><td colspan="5">No hay mantenimientos registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($items as $index => $item): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($item['marca']) ?></td>
                                        <td><?= htmlspecialchars($item['tipo_equipo']) ?></td>
                                        <td><?= htmlspecialchars($item['estado']) ?></td>
                                        <td>
                                            <div class="actions">
                                                <a class="btn btn-muted" href="panel_admin.php?seccion=pendientes&accion=ver&id=<?= (int)$item['id'] ?>">Visualizar</a>
                                                <a class="btn btn-primary" href="panel_admin.php?seccion=pendientes&accion=editar&id=<?= (int)$item['id'] ?>">Editar</a>
                                                <a class="btn btn-muted" target="_blank" href="ticket_impresion.php?id=<?= (int)$item['id'] ?>">Generar Recibo</a>
                                                <form method="post" action="panel_admin.php?seccion=pendientes&accion=eliminar&id=<?= (int)$item['id'] ?>" onsubmit="return confirm('Seguro que deseas eliminar este mantenimiento?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                    <button class="btn btn-danger" type="submit">Eliminar</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($seccion === 'pendientes' && $accion === 'ver' && $id > 0): ?>
                <?php $detalle = obtenerMantenimientoPorId($pdo, $id); ?>
                <?php if (!$detalle): ?>
                    <p>Mantenimiento no encontrado.</p>
                <?php else: ?>
                    <h1 class="page-title">Visualizar mantenimiento</h1>
                    <div class="readonly-box">
                        <p><strong>Fecha ingreso:</strong> <?= htmlspecialchars($detalle['fecha_ingreso']) ?></p>
                        <p><strong>Marca:</strong> <?= htmlspecialchars($detalle['marca']) ?></p>
                        <p><strong>Token:</strong> <?= htmlspecialchars($detalle['token']) ?></p>
                        <p><strong>Email cliente:</strong> <?= htmlspecialchars((string)($detalle['email_cliente'] ?? 'No registrado')) ?></p>
                        <p><strong>WhatsApp cliente:</strong> <?= htmlspecialchars($detalle['whatsapp_cliente']) ?></p>
                        <p><strong>Tipo:</strong> <?= htmlspecialchars($detalle['tipo_equipo']) ?></p>
                        <p><strong>Razon:</strong> <?= nl2br(htmlspecialchars($detalle['razon_servicio'])) ?></p>
                        <p><strong>Diagnostico cliente:</strong> <?= nl2br(htmlspecialchars($detalle['diagnostico_actual'] ?: 'Sin diagnostico.')) ?></p>
                        <p><strong>Fecha entrega:</strong> <?= htmlspecialchars($detalle['fecha_entrega']) ?></p>
                        <p><strong>Estado:</strong> <?= htmlspecialchars($detalle['estado']) ?></p>
                        <p><strong>Costo estimado:</strong> <?= $detalle['costo_estimado'] !== null ? '$ ' . htmlspecialchars((string)$detalle['costo_estimado']) : 'No definido' ?></p>
                        <p><strong>Presupuesto:</strong> <?= htmlspecialchars($detalle['estado_presupuesto']) ?></p>
                    </div>
                    <?php if (!empty($detalle['ruta_imagen'])): ?>
                        <div class="readonly-box" style="margin-top: 12px;">
                            <p><strong>Evidencia fotografica:</strong></p>
                            <img src="<?= htmlspecialchars($detalle['ruta_imagen']) ?>" alt="Evidencia del mantenimiento" class="evidence-image">
                        </div>
                    <?php endif; ?>
                    <div class="actions" style="margin-top: 14px;">
                        <button
                            type="button"
                            class="btn btn-whatsapp"
                            onclick='window.PcSaludUI.notificarWhatsApp({
                                token: <?= json_encode((string)$detalle["token"]) ?>,
                                marca: <?= json_encode((string)$detalle["marca"]) ?>,
                                diagnostico: <?= json_encode((string)($detalle["diagnostico_actual"] ?: "Sin diagnostico")) ?>,
                                fechaEntrega: <?= json_encode((string)$detalle["fecha_entrega"]) ?>,
                                whatsapp: <?= json_encode((string)$detalle["whatsapp_cliente"]) ?>
                            })'
                        >
                            Notificar por WhatsApp
                        </button>
                    </div>
                    <a class="back-link" href="panel_admin.php?seccion=pendientes">Volver al listado</a>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($seccion === 'pendientes' && $accion === 'editar' && $id > 0): ?>
                <?php $editar = obtenerMantenimientoPorId($pdo, $id); ?>
                <?php if (!$editar): ?>
                    <p>Mantenimiento no encontrado.</p>
                <?php else: ?>
                    <?php $detallesEdicion = obtenerDetallesPresupuesto($pdo, (int)$editar['id']); ?>
                    <h1 class="page-title">Editar mantenimiento</h1>
                    <p class="page-subtitle">Edita diagnostico para cliente y fecha de entrega.</p>

                    <div class="readonly-box">
                        <p><strong>Fecha ingreso:</strong> <?= htmlspecialchars($editar['fecha_ingreso']) ?></p>
                        <p><strong>Marca:</strong> <?= htmlspecialchars($editar['marca']) ?></p>
                        <p><strong>Token:</strong> <?= htmlspecialchars($editar['token']) ?></p>
                        <p><strong>Email cliente:</strong> <?= htmlspecialchars((string)($editar['email_cliente'] ?? 'No registrado')) ?></p>
                        <p><strong>WhatsApp cliente:</strong> <?= htmlspecialchars($editar['whatsapp_cliente']) ?></p>
                    </div>

                    <form method="post" enctype="multipart/form-data" style="margin-top: 16px;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <div class="field-group">
                            <label for="diagnostico_actual">Diagnostico (cliente)</label>
                            <textarea id="diagnostico_actual" name="diagnostico_actual"><?= htmlspecialchars($editar['diagnostico_actual']) ?></textarea>
                        </div>
                        <div class="field-group">
                            <label for="fecha_entrega">Fecha de entrega</label>
                            <input id="fecha_entrega" type="date" name="fecha_entrega" value="<?= htmlspecialchars($editar['fecha_entrega']) ?>" required>
                        </div>
                        <div class="field-group">
                            <label for="estado">Estado del mantenimiento</label>
                            <select id="estado" name="estado" required>
                                <?php foreach (estadosPermitidos() as $opcionEstado): ?>
                                    <option value="<?= htmlspecialchars($opcionEstado) ?>" <?= $editar['estado'] === $opcionEstado ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($opcionEstado) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>Desglose de costos</label>
                            <div id="costosContainer">
                                <?php if (!empty($detallesEdicion)): ?>
                                    <?php foreach ($detallesEdicion as $detalleCosto): ?>
                                        <div class="cost-row">
                                            <input type="text" name="concepto[]" value="<?= htmlspecialchars($detalleCosto['concepto']) ?>" placeholder="Concepto">
                                            <input type="number" name="costo[]" value="<?= htmlspecialchars((string)$detalleCosto['costo']) ?>" min="0" step="0.01" placeholder="Costo">
                                            <button type="button" class="btn btn-danger btn-sm remove-row">Quitar</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="cost-row">
                                        <input type="text" name="concepto[]" placeholder="Concepto">
                                        <input type="number" name="costo[]" min="0" step="0.01" placeholder="Costo">
                                        <button type="button" class="btn btn-danger btn-sm remove-row">Quitar</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn btn-primary" id="btnAddConcepto">Anadir Concepto</button>
                        </div>
                        <div class="field-group">
                            <label for="imagen_evidencia">Foto de evidencia</label>
                            <input id="imagen_evidencia" type="file" name="imagen_evidencia" accept="image/*">
                        </div>
                        <?php if (!empty($editar['ruta_imagen'])): ?>
                            <div class="readonly-box">
                                <p><strong>Evidencia actual:</strong></p>
                                <img src="<?= htmlspecialchars($editar['ruta_imagen']) ?>" alt="Evidencia actual" class="evidence-image">
                            </div>
                        <?php endif; ?>
                        <button class="btn btn-primary" type="submit">Guardar cambios</button>
                        <a class="btn btn-muted" href="panel_admin.php?seccion=pendientes">Cancelar</a>
                    </form>
                    <div class="actions" style="margin-top: 12px;">
                        <button
                            type="button"
                            class="btn btn-whatsapp"
                            onclick='window.PcSaludUI.notificarWhatsApp({
                                token: <?= json_encode((string)$editar["token"]) ?>,
                                marca: <?= json_encode((string)$editar["marca"]) ?>,
                                whatsapp: <?= json_encode((string)$editar["whatsapp_cliente"]) ?>,
                                diagnosticoSelector: "#diagnostico_actual",
                                fechaSelector: "#fecha_entrega"
                            })'
                        >
                            Notificar por WhatsApp
                        </button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
    <script src="js/ui.js"></script>
    <script>
        window.PcSaludUI.initTheme();
        (function () {
            const container = document.getElementById('costosContainer');
            const btnAdd = document.getElementById('btnAddConcepto');
            if (!container || !btnAdd) return;

            function crearFila() {
                const row = document.createElement('div');
                row.className = 'cost-row';
                row.innerHTML = '<input type="text" name="concepto[]" placeholder="Concepto">' +
                    '<input type="number" name="costo[]" min="0" step="0.01" placeholder="Costo">' +
                    '<button type="button" class="btn btn-danger btn-sm remove-row">Quitar</button>';
                return row;
            }

            btnAdd.addEventListener('click', function () {
                container.appendChild(crearFila());
            });

            container.addEventListener('click', function (e) {
                if (!e.target.classList.contains('remove-row')) return;
                const rows = container.querySelectorAll('.cost-row');
                if (rows.length <= 1) {
                    rows[0].querySelectorAll('input').forEach(function (input) {
                        input.value = '';
                    });
                    return;
                }
                e.target.closest('.cost-row').remove();
            });
        })();
    </script>
</body>
</html>

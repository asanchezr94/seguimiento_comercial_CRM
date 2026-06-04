# Informe y manual de usuario - Seguimiento Comercial

Fecha de actualización: 31 de mayo de 2026
Proyecto: seguimiento_comercial
Tecnología: Laravel 12, PHP 8.2, MySQL/MariaDB, Blade, XAMPP/local hosting

## 1. Resumen ejecutivo

Seguimiento Comercial es una aplicación web construida en Laravel para apoyar la gestión de asesores comerciales, supervisores y bases de clientes. La aplicación permite cargar bases asignadas, distribuirlas entre comerciales, registrar gestiones, controlar cierres, aprobar solicitudes desde supervisión, gestionar clientes potenciales, programar visitas, medir desempeño mediante dashboard y controlar estados posteriores al cierre como desembolso.

El sistema está pensado para operar con dos roles principales: supervisor y comercial. El supervisor administra bases, usuarios, metas, aprobaciones e indicadores globales. El comercial gestiona sus registros asignados, registra avances, solicita cierres, consulta sus pendientes y controla sus visitas.

## 2. Objetivo de la aplicación

Centralizar el seguimiento comercial para que una empresa pueda:

- Cargar bases comerciales en lote desde CSV.
- Asignar o distribuir registros a asesores comerciales.
- Registrar gestiones comerciales con estado, detalle, canal, tiempo invertido y próxima gestión.
- Controlar solicitudes de cierre con aprobación del supervisor.
- Gestionar clientes potenciales creados manualmente por los usuarios.
- Medir indicadores comerciales por mes, asesor, canal, estado, monto y desembolso.
- Programar y registrar visitas comerciales.
- Gestionar metas mensuales por asesor.
- Mantener trazabilidad de cada gestión y de cada cambio relevante.

## 3. Roles del sistema

### 3.1 Supervisor

El supervisor tiene permisos para:

- Ver dashboard general.
- Cargar bases masivas.
- Crear registros manuales de base asignada.
- Asignar bases por lote a uno o varios comerciales.
- Consultar todos los lotes y registros.
- Gestionar también sus propias bases si se asigna registros.
- Ver todos los comerciales y su gestión.
- Aprobar o devolver cierres solicitados por comerciales.
- Aprobar o devolver cambios de estado de desembolso.
- Asignar metas mensuales por asesor.
- Ver acumulados generales por usuario.
- Ver indicadores mensuales y por canal/origen.
- Programar y consultar visitas propias o de otros asesores.

### 3.2 Comercial

El comercial tiene permisos para:

- Ver únicamente sus bases/lotes asignados.
- Gestionar registros asignados.
- Crear clientes potenciales.
- Gestionar clientes potenciales propios.
- Solicitar cierre de registros.
- Consultar sus gestiones pendientes por aprobar.
- Consultar sus registros cerrados.
- Solicitar cambios de estado de desembolso después del cierre.
- Programar y registrar visitas propias.
- Consultar su meta mensual e indicadores personales.

## 4. Módulos desarrollados

### 4.1 Autenticación y roles

Se creó un sistema de login propio con control de sesión. Los usuarios tienen un campo de rol que permite diferenciar entre supervisor y comercial.

Funciones principales:

- Inicio de sesión.
- Cierre de sesión.
- Restricción de rutas según rol.
- Vistas diferenciadas para supervisor y comercial.

### 4.2 Base asignada

Este módulo administra los registros cargados por la empresa y asignados a comerciales.

Funciones principales:

- Carga manual de registros.
- Carga masiva desde CSV.
- Organización por lote.
- Visualización de lotes en el índice principal.
- Filtros por nombre de lote y origen.
- Consulta de detalle de lote.
- Filtros dentro del lote por estado, nombre, cédula o celular.
- Asignación masiva por lote a uno o varios comerciales.
- Distribución de registros entre comerciales.
- Protección para no reasignar registros que ya tienen gestión.
- Registro de cédula, línea de crédito, empresa, teléfono, email y observaciones.

### 4.3 Carga masiva CSV

El supervisor puede cargar bases por archivo CSV desde un modal.

Formato recomendado del CSV:

| Columna | Obligatoria | Descripción |
|---|---:|---|
| nombre | Sí | Nombre del cliente |
| telefono | Sí | Teléfono o celular |
| cedula | No | Documento del cliente |
| linea_credito | No | Línea de crédito solicitada |
| email | No | Correo del cliente |
| empresa | No | Empresa o entidad asociada |
| observaciones | No | Comentarios iniciales |
| estado_slug | No | Estado inicial del registro |
| comercial_email | No | Email del comercial al que se asigna |

Notas:

- El correo del cliente es opcional.
- Si `comercial_email` queda vacío, el registro queda sin asignar y puede distribuirse luego por lote.
- La línea de crédito debe coincidir con las opciones configuradas.
- La aplicación incluye descarga de plantilla CSV desde el modal de carga.

### 4.4 Clientes potenciales

Este módulo permite que los usuarios creen oportunidades propias fuera de la base asignada.

Funciones principales:

- Crear cliente potencial desde modal.
- Registrar nombre, cédula, línea de crédito, teléfono, email, empresa, origen y observaciones.
- Filtrar por estado, nombre o celular.
- Gestionar cliente potencial igual que un registro de base asignada.
- Solicitar cierre con aprobación de supervisor.
- Incluir los clientes potenciales en indicadores del dashboard.
- Incluir clientes potenciales en históricos asociados.
- Incluir clientes potenciales en mis cerrados.

### 4.5 Gestión comercial

Cada registro asignado o cliente potencial puede tener historial de gestiones.

Campos principales de una gestión:

- Tipo de gestión: visita, oficina, llamada, redes sociales.
- Estado resultante.
- Línea de crédito.
- Próxima gestión con fecha y hora.
- Monto solicitado.
- Tiempo invertido en minutos.
- Detalle de gestión.
- Efectivo SI/NO cuando se solicita cierre.
- Monto aprobado cuando el cierre es efectivo.
- Estado de desembolso al solicitar cierre.

Reglas importantes:

- El comercial puede gestionar registros que tiene asignados.
- El supervisor puede gestionar registros si tiene permisos o si se asigna registros.
- Cuando un registro se marca como cerrado, no queda cerrado directamente.
- Al solicitar cierre, el registro pasa a pendiente de aprobación del supervisor.
- El supervisor puede aprobar o devolver la solicitud.
- Si aprueba, el registro queda cerrado.
- Si devuelve, el registro queda en estado devuelta y el comercial puede corregir.
- Cuando el registro queda cerrado, ya no se permite editar la gestión comercial principal.

### 4.6 Estados comerciales

El sistema maneja estados comerciales para representar el avance del registro.

Estados usados durante el flujo:

- Nuevo.
- Contactado.
- Cerrado.
- Pendiente de aprobación supervisor.
- Devuelta.
- Otros estados activos configurados en la tabla de estados.

Nota funcional:

`Pendiente de aprobación supervisor` no debe asignarse manualmente; lo asigna automáticamente el sistema cuando un comercial solicita cierre.

### 4.7 Cierres y aprobación del supervisor

El cierre tiene un flujo de control para evitar que un comercial cierre registros sin revisión.

Flujo:

1. El comercial gestiona el registro.
2. Selecciona estado `Cerrado`.
3. El sistema exige seleccionar `Efectivo SI/NO`.
4. Si es efectivo SI, exige monto aprobado.
5. El sistema exige estado de desembolso.
6. El registro queda como `Pendiente de aprobación supervisor`.
7. El supervisor revisa la solicitud.
8. El supervisor puede aprobar o devolver.
9. Si aprueba, el registro queda cerrado.
10. Si devuelve, el registro queda devuelto con motivo.

### 4.8 Estado de desembolso

Se agregó un estado independiente llamado `Estado desembolso`. Este no reemplaza el estado comercial, sino que complementa los registros cerrados.

Estados de desembolso disponibles:

- Por desembolsar.
- Desembolsado.
- Aplazado.
- Negados.
- Desistido.
- Pendiente de radicar.

Reglas:

- El estado de desembolso se solicita al momento del cierre.
- Después de que el registro está cerrado, el estado de desembolso puede seguir cambiando.
- Si el cambio lo solicita un comercial, debe aprobarlo el supervisor.
- Si el cambio lo hace el supervisor, se actualiza directamente.
- Las solicitudes pendientes de desembolso aparecen en la sección de gestiones pendientes por aprobar.

### 4.9 Mis cerrados

Se creó una sección para que comercial y supervisor consulten sus registros cerrados.

Incluye:

- Registros de base asignada cerrados.
- Clientes potenciales cerrados.
- Lote u origen.
- Cliente.
- Cédula.
- Línea de crédito.
- Monto solicitado.
- Monto aprobado.
- Fecha de asignación.
- Última modificación.
- Acción para ver detalle.

### 4.10 Gestiones pendientes por aprobar

Sección del supervisor para revisar solicitudes pendientes.

Incluye:

- Cierres pendientes de base asignada.
- Cierres pendientes de clientes potenciales.
- Cambios de desembolso pendientes de base asignada.
- Cambios de desembolso pendientes de clientes potenciales.

Acciones disponibles:

- Gestionar/ver detalle.
- Aprobar.
- Devolver con motivo.

### 4.11 Dashboard

El dashboard concentra indicadores mensuales, históricos y por usuario.

Indicadores mensuales:

- Registros cargados.
- Gestión mensual.
- Cierre mensual.
- Cierres efectivos.
- Cierres no efectivos.
- Monto colocado.
- Monto solicitado.
- Diferencia entre monto colocado y solicitado.

Indicadores por canal/origen:

- Gestiones por origen/canal.
- Registros únicos gestionados.
- Porcentaje sobre gestiones del mes.
- Cierres por canal/origen.
- Solicitudes de cierre.
- Registros únicos.
- Cierres aprobados.
- No efectivos.
- Monto solicitado.
- Monto aprobado.

Rendimiento por comercial:

- Asignados del mes.
- Gestionados.
- Pendientes de aprobación.
- Cierres del mes.
- Cierre mensual.
- Porcentaje de gestión.
- Efectivo SI.
- Efectivo NO.
- Porcentaje efectivo SI.
- Porcentaje no efectivo.
- Monto solicitado del mes.
- Tiempo invertido.
- Promedio tiempo a cierre.
- Monto colocado.

Acumulado general por usuario:

- Total asignados.
- Gestionados.
- Porcentaje de gestión.
- Cerrados.
- Porcentaje de cierre.
- Pendientes de aprobación.
- Efectivo SI.
- Efectivo NO.
- Monto solicitado.
- Monto aprobado.

Indicadores de desembolso:

- Estados de desembolso vs cierres.
- Cierres efectivos vs estados de desembolso.
- Comparación entre efectivos y no efectivos por estado de desembolso.

Indicadores de visitas:

- Visitas programadas por persona.
- Visitas registradas.
- Visitas realizadas.
- Visitas canceladas.
- Visitas pendientes.
- Porcentaje de visitas realizadas.

### 4.12 Metas mensuales por asesor

El supervisor puede asignar metas mensuales por asesor.

Campos:

- Asesor.
- Mes.
- Año.
- Monto meta.

Visualización en dashboard:

- Asesor.
- Monto colocado.
- Monto meta.
- Restante.
- Porcentaje de cumplimiento.
- Estado: cumple o no cumple.

Reglas:

- La meta se guarda por asesor, mes y año.
- No se sobreescriben otros meses.
- Si se registra una meta para el mismo asesor, mes y año, se actualiza ese registro.
- El comercial solo ve su propia meta.

### 4.13 Visitas

Se creó un módulo de visitas para programar y registrar visitas comerciales.

Funciones:

- Programar visita con cliente, teléfono, dirección, motivo, fecha y hora inicio, fecha y hora fin.
- Ver visitas en calendario mensual.
- Supervisor puede ver/programar visitas para cualquier asesor.
- Comercial ve y programa sus propias visitas.
- Registrar resultado de la visita.
- Marcar visita como realizada o cancelada.
- Dashboard muestra visitas por persona.
- La vista se actualiza automáticamente cada 60 segundos si no hay modal abierto.

### 4.14 Notificaciones

Se agregó una sección de notificaciones para eventos relevantes.

Eventos que generan notificaciones:

- Solicitud de cierre pendiente.
- Aprobación de gestión.
- Devolución de gestión.
- Próxima gestión programada para hoy.
- Solicitud de cambio de desembolso pendiente.

### 4.15 Histórico asociado

Se creó consulta de históricos asociados por cédula, teléfono o nombre.

Permite ver:

- Registros de base asignada relacionados.
- Clientes potenciales relacionados.
- Historial de gestiones asociado.
- Comercial asignado.
- Estado actual.
- Acción para abrir el registro.

### 4.16 Diseño e interfaz

Mejoras realizadas:

- Modales para crear base asignada, cargar base, clientes potenciales, metas y visitas.
- Fondo oscuro en modales para enfocar la acción.
- Encabezados de tablas con azul más visible.
- Tablas paginadas a 10 registros.
- Filtros por estado, nombre, cédula, celular, lote u origen según sección.
- Dashboard con filtro global sticky por mes y año.
- Botones de acción agrupados para mejorar lectura.

## 5. Manual de usuario - Supervisor

### 5.1 Iniciar sesión

1. Abrir la URL de la aplicación.
2. Ingresar correo y contraseña.
3. Presionar iniciar sesión.
4. Verificar que aparezca el menú principal.

### 5.2 Cargar una base masiva

1. Ir a `Base asignada`.
2. Presionar `Cargar base`.
3. Descargar la plantilla CSV si se necesita.
4. Completar el archivo con las columnas permitidas.
5. Escribir el nombre del lote.
6. Seleccionar origen.
7. Seleccionar el archivo CSV.
8. Presionar guardar/cargar.
9. Revisar el mensaje de creados y omitidos.

### 5.3 Crear registro manual de base asignada

1. Ir a `Base asignada`.
2. Presionar `+ Nueva base asignada`.
3. Completar nombre, teléfono, cédula, línea de crédito, empresa, origen y demás campos.
4. Seleccionar comercial si se desea asignar de inmediato.
5. Guardar.

### 5.4 Asignar un lote a comerciales

1. Ir a `Base asignada`.
2. Abrir el lote deseado.
3. Seleccionar uno o varios comerciales.
4. Presionar asignar/distribuir.
5. El sistema reparte registros sin gestión entre los comerciales seleccionados.

### 5.5 Revisar gestiones pendientes

1. Ir a `Gestiones pendientes por aprobar`.
2. Revisar cierres pendientes de base asignada o clientes potenciales.
3. Presionar `Gestionar` para ver el detalle completo.
4. Si todo está correcto, presionar `Aprobar`.
5. Si falta información, presionar `Devolver` y escribir el motivo.

### 5.6 Revisar cambios de desembolso pendientes

1. Ir a `Gestiones pendientes por aprobar`.
2. Bajar a las tablas de desembolsos pendientes.
3. Revisar estado actual y estado solicitado.
4. Presionar `Gestionar` si se requiere contexto.
5. Presionar `Aprobar` o `Devolver`.

### 5.7 Asignar meta mensual

1. Ir a `Dashboard`.
2. Seleccionar mes y año en el filtro superior.
3. Presionar `Asignar meta`.
4. Seleccionar asesor.
5. Confirmar mes y año.
6. Escribir monto meta.
7. Guardar.

### 5.8 Consultar dashboard

1. Ir a `Dashboard`.
2. Seleccionar mes y año.
3. Revisar metas, indicadores, cierres, visitas y acumulados.
4. Usar los enlaces en indicadores para ver detalles cuando estén disponibles.

### 5.9 Programar visitas

1. Ir a `Visitas`.
2. Seleccionar mes y año si se desea.
3. Presionar `Programar visita`.
4. Seleccionar asesor.
5. Completar cliente, teléfono, dirección, motivo, hora inicio y hora fin.
6. Guardar.

### 5.10 Registrar resultado de visita

1. Ir a `Visitas`.
2. Buscar la visita en el calendario.
3. Presionar `Registrar`.
4. Seleccionar realizada o cancelada.
5. Escribir resultado.
6. Guardar.

## 6. Manual de usuario - Comercial

### 6.1 Consultar bases asignadas

1. Iniciar sesión.
2. Ir a `Base asignada`.
3. Ver los lotes asignados.
4. Abrir un lote.
5. Usar filtros por estado, nombre, cédula o celular.
6. Presionar `Gestionar` sobre el cliente deseado.

### 6.2 Registrar una gestión

1. Abrir un registro asignado.
2. Seleccionar tipo de gestión.
3. Seleccionar estado resultante si aplica.
4. Ajustar línea de crédito si aplica.
5. Registrar próxima gestión si se necesita.
6. Registrar monto solicitado si aplica.
7. Registrar tiempo invertido.
8. Escribir detalle de la gestión.
9. Guardar.

### 6.3 Solicitar cierre

1. Abrir el registro.
2. En estado resultante, seleccionar `Cerrado`.
3. Seleccionar si fue efectivo SI o NO.
4. Si fue efectivo SI, escribir monto aprobado.
5. Seleccionar estado de desembolso.
6. Escribir detalle.
7. Guardar.
8. El registro queda pendiente de aprobación del supervisor.

### 6.4 Corregir una gestión devuelta

1. Ir al registro devuelto.
2. Leer el motivo de devolución del supervisor.
3. Registrar una nueva gestión con la corrección.
4. Solicitar cierre nuevamente si corresponde.

### 6.5 Cambiar estado de desembolso después del cierre

1. Abrir un registro cerrado.
2. Ir a `Estado de desembolso`.
3. Seleccionar nuevo estado.
4. Escribir detalle opcional.
5. Presionar `Solicitar cambio de desembolso`.
6. Esperar aprobación del supervisor.

### 6.6 Crear cliente potencial

1. Ir a `Clientes potenciales`.
2. Presionar `+ Nuevo cliente potencial`.
3. Completar nombre, cédula, línea de crédito, teléfono, empresa, origen y observaciones.
4. Guardar.
5. Abrir el cliente para registrar gestiones.

### 6.7 Consultar mis cerrados

1. Ir a `Mis cerrados`.
2. Revisar registros cerrados de base asignada y clientes potenciales.
3. Usar filtros por estado o nombre/celular si están disponibles.
4. Presionar `Ver` para abrir detalle.

### 6.8 Programar y registrar visitas

1. Ir a `Visitas`.
2. Presionar `Programar visita`.
3. Completar datos de visita.
4. Guardar.
5. Cuando se realice la visita, presionar `Registrar`.
6. Escribir resultado y guardar.

### 6.9 Consultar mi dashboard

1. Ir a `Dashboard`.
2. Seleccionar mes y año.
3. Revisar meta personal.
4. Revisar indicadores personales.
5. Revisar visitas del mes.

## 7. Reglas de negocio principales

- Un comercial solo ve sus registros asignados.
- Un supervisor ve todos los registros.
- Un supervisor también puede gestionar bases si se asigna registros.
- Los cierres solicitados por comerciales requieren aprobación.
- El estado `Pendiente de aprobación supervisor` lo asigna el sistema automáticamente.
- Los registros cerrados no permiten edición comercial normal.
- El estado de desembolso puede cambiar después del cierre.
- Si el comercial solicita cambio de desembolso, requiere aprobación.
- Las metas son mensuales por asesor.
- Los clientes potenciales cuentan en indicadores igual que las bases asignadas.
- Las visitas cuentan en indicadores de visitas del dashboard.

## 8. Principales tablas de base de datos

| Tabla | Uso principal |
|---|---|
| users | Usuarios y roles |
| estados | Estados comerciales |
| base_asignadas | Registros cargados/asignados por lote |
| cliente_potencials | Clientes creados por usuarios |
| gestions | Historial de gestiones |
| app_notifications | Notificaciones internas |
| personas | Asociación por cédula/persona |
| metas_comerciales | Metas mensuales por asesor |
| visitas | Programación y registro de visitas |

## 9. Cambios de base de datos recientes importantes

Columnas agregadas para clientes potenciales:

- cedula.
- linea_credito.
- monto_solicitado.
- efectivo.
- monto_linea_credito.
- cierre_solicitado_at.
- cierre_solicitado_por.
- cierre_aprobado_at.
- motivo_devolucion.
- ultima_gestion_at.

Columnas agregadas para desembolso en base asignada y clientes potenciales:

- desembolso_estado.
- desembolso_estado_pendiente.
- desembolso_solicitado_at.
- desembolso_solicitado_por.
- desembolso_aprobado_at.
- desembolso_motivo_devolucion.

Tabla agregada para visitas:

- visitas.

Columnas principales de visitas:

- user_id.
- titulo.
- cliente_nombre.
- telefono.
- direccion.
- programada_at.
- finaliza_at.
- estado.
- resultado.
- registrada_at.

## 10. Recomendaciones para despliegue en hosting

Antes de subir cambios al hosting:

1. Sacar copia completa del proyecto.
2. Exportar copia de la base de datos desde phpMyAdmin.
3. Subir archivos actualizados.
4. Aplicar cambios SQL necesarios si no se pueden correr migraciones.
5. Limpiar cachés de Laravel si aplica.
6. Probar login, dashboard, creación de registros, cierre y aprobación.

Comandos útiles en hosting si se tiene consola:

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

Si se pueden correr migraciones:

```bash
php artisan migrate
```

Si no se pueden correr migraciones, aplicar manualmente los `ALTER TABLE` correspondientes en phpMyAdmin.

## 11. Pendientes sugeridos para próximas mejoras

- Exportar dashboard a Excel o PDF.
- Crear administración visual de usuarios desde la aplicación.
- Crear administración visual de estados comerciales.
- Crear auditoría completa de cambios críticos.
- Agregar filtros avanzados en dashboard por asesor, origen y lote.
- Agregar gráfico visual para embudo comercial.
- Agregar recordatorios automáticos más avanzados para próximas gestiones y visitas.
- Agregar campo de observación específica para aprobación/devolución de desembolso.
- Unificar tablas de desembolso del dashboard si se desea una vista más compacta.

## 12. Conclusión

La aplicación evolucionó desde una estructura básica de Laravel hacia un sistema funcional de seguimiento comercial con gestión de bases, clientes potenciales, cierres aprobados por supervisor, metas, visitas, dashboard y control de desembolso. El flujo actual permite tener trazabilidad de cada gestión, controlar la calidad de los cierres y medir el desempeño comercial por asesor y por periodo.

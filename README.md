# Control de Vacunas – Tablero Web (Grupo 020)

Tablero web en PHP que lee el archivo Excel y muestra las existencias de vacunas por región de Coahuila.

---

## Requisitos

- PHP 8.1+
- Composer (gestor de dependencias PHP)
- Extensiones PHP: `zip`, `xml`, `gd` (para PhpSpreadsheet)

---

## Instalación rápida

1. **Descarga / clona** esta carpeta en tu servidor web (Apache, Nginx, o usa el servidor integrado de PHP).

2. **Instala dependencias** (solo la primera vez):
   ```bash
   cd dashboard/
   composer install
   ```

3. **Coloca el archivo Excel** en la misma carpeta:
   ```
   dashboard/
   ├── index.php
   ├── composer.json
   ├── vendor/           ← generado por composer install
   └── Control_Existencias_por_Unidad_18.xlsx   ← aquí va tu archivo
   ```

4. **Inicia el servidor** (desarrollo local):
   ```bash
   php -S localhost:8080 -t dashboard/
   ```

5. Abre en tu navegador: `http://localhost:8080`

---

## Actualización semanal

Cada semana simplemente **reemplaza** el archivo Excel con la versión nueva:

```
Control_Existencias_por_Unidad_18.xlsx
```

El tablero detecta el cambio y regenera el caché automáticamente en la próxima visita.

> El caché se guarda en `cache_data.json` y se invalida cada **1 hora**.  
> Para forzar una actualización inmediata, elimina el archivo `cache_data.json`.

---

## Estructura de datos leída del Excel

| Columna (0-indexed) | Nombre               | Uso en el tablero        |
|---------------------|----------------------|--------------------------|
| 5                   | NOMBRE UNIDAD        | Agrupación por región    |
| 17                  | DESCRIPCION          | Nombre de la vacuna      |
| 27                  | UNIDAD               | Existencias en unidad    |
| 34                  | UNIDAD.1 (Almacén)   | Existencias en almacén   |

---

## Regiones de Coahuila

Las unidades médicas se agrupan en 5 regiones según su número:

| Región       | Unidades principales                              |
|--------------|---------------------------------------------------|
| CARBONÍFERA  | UMF 14, 23, 31, 32, 50, 60, 61, 62, 64           |
| CENTRO       | UMF 4, 9, 25, 28, 29, 80–88, UMAA 89, HGZ-MF 2/16|
| LAGUNA       | UMF 15, 26, HGZ 11, HGSZ-MF 20/21, HGZ-MF 18/24 |
| NORTE        | UMF 3, 8, 12, 70, 74, HRS Ramos, San Buenaventura|
| SUR          | UMF 10, UMR-EM 52, HGSZ-MF 27                    |

> Para ajustar el mapeo, edita la función `getRegion()` en `index.php`.

---

## Funcionalidades del tablero

- **Botones de región** en la barra superior (CARBONÍFERA, CENTRO, LAGUNA, NORTE, SUR)
- **4 tarjetas de resumen**: total existencias, tipos de vacuna, unidades médicas, vacunas sin stock
- **Gráfica de dona** con existencias por tipo de vacuna
- **Leyenda interactiva** con valores por vacuna
- **Lista de unidades** de la región seleccionada
- **Tabla ordenable** por cualquier columna (clic en encabezado)
- **Buscador** de vacunas en tiempo real
- **Indicadores visuales**: rojo = sin stock, amarillo = stock bajo (<20)

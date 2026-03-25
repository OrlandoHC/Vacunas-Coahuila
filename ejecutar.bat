@echo off
title Gestor de Inventario - Actualización
color 0B
cls

echo ======================================================
echo           SISTEMA DE CONTROL DE EXISTENCIAS
echo ======================================================
echo.

:: 1. Localizar el archivo de Excel
echo [1/3] Buscando archivo de Excel para renombrar...
set "found="
for %%f in (*.xlsx) do (
    if /I not "%%f"=="Control_Existencias_por_Unidad.xlsx" (
        set "oldName=%%f"
        goto :rename_file
    )
)

:rename_file
if defined oldName (
    echo      ^> Renombrando "%oldName%"...
    ren "%oldName%" "Control_Existencias_por_Unidad.xlsx"
    if %errorlevel% equ 0 (
        echo      ^> [OK] Archivo renombrado con exito.
    ) else (
        color 0C
        echo      ^> [ERROR] No se pudo renombrar. Verifica si esta abierto.
        pause
        exit
    )
) else (
    echo      ^> [!] No se encontraron archivos nuevos o ya esta renombrado.
)

echo.

:: 2. Ejecutar el script de Python
echo [2/3] Iniciando proceso de actualizacion (actualizar.py)...
echo      ^> Por favor, espere...
echo.

python actualizar.py

if %errorlevel% equ 0 (
    echo.
    echo [3/3] Proceso completado correctamente.
    color 0A
) else (
    echo.
    color 0C
    echo [3/3] Hubo un error durante la ejecucion de Python.
)

echo.
echo ======================================================
echo    Presione cualquier tecla para salir...
echo ======================================================
pause > nul
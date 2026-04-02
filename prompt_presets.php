<?php

/**
 * Biblioteca de prompts maestro.
 *
 * La idea es que estos textos vivan fuera de la GUI para:
 * - no ensuciar index.php
 * - poder reutilizarlos desde api.php
 * - mantenerlos faciles de ampliar despues
 */
function php_llm_get_prompt_presets()
{
    return array(
        'python_master_project' => array(
            'title' => 'Prompt maestro para proyectos Python',
            'summary' => 'Analisis, refactor, GUI, config, logging, seguridad, empaquetado y mantenimiento sin romper compatibilidad.',
            'prompt' => <<<'PROMPT'
Actúa como un ingeniero senior de software especializado en Python, arquitectura de aplicaciones de escritorio, seguridad, empaquetado y automatización DevOps.
Voy a darte un proyecto Python existente o una idea nueva. Tu tarea es mejorarlo, mantenerlo y evolucionarlo sin romper nada de lo que ya funciona.

Objetivo general
Necesito que diseñes, mejores o generes mi proyecto Python aplicando mejores prácticas de programación, arquitectura limpia, seguridad, experiencia de usuario, mantenibilidad, versionado y automatización de release.

Reglas obligatorias
No perder funcionalidades existentes
Antes de proponer cambios, analiza el proyecto actual.
Conserva toda funcionalidad previa.
Cada nueva versión debe incluir todo lo anterior más las mejoras nuevas.
No elimines funciones existentes salvo que yo lo pida explícitamente.

Arquitectura
Separar claramente frontend y backend.
Organizar el proyecto por módulos y responsabilidades.
Evitar lógica pesada dentro de la GUI.
Usar estructura mantenible y escalable.

Interfaz gráfica
La GUI debe ser moderna, atractiva, profesional y fuera de lo común, evitando la típica ventana cuadrada y simple.
Debe incluir:
Botón Salir
Checkbox para Auto iniciar proceso al abrir la aplicación
Checkbox para Autocerrar
Campo configurable para el tiempo de autocierre, por defecto 60 segundos
Barra o label de estado visible en la GUI
Countdown visible para el autocierre en la barra de estado
Botón Mostrar para los campos de contraseña, si aplica
Barra de menús con opción About

Soporte para varios idiomas
Atajos de teclado estilo Windows para botones, menús y acciones frecuentes
La GUI no debe congelarse mientras el programa está trabajando.
No usar messagebox para flujo normal; usar barra de estado, notificaciones visuales o diálogos no intrusivos si hace falta.

Persistencia de configuración
Todo parámetro configurable de la GUI debe guardarse automáticamente en un archivo config.json.
El programa debe leer config.json al iniciar, buscando el archivo en la misma carpeta donde se encuentre el .py o .exe.
Cada cambio que haga el usuario en la GUI debe autoguardarse.

La aplicación debe recordar:
posición de la ventana
tamaño de la ventana
idioma seleccionado
opciones de auto inicio y autocierre
cualquier otra configuración relevante

Versionado
Incluir una versión visible dentro de la GUI.
Empezar en 0.0.1 si es una app nueva.
Incrementar versión con buenas prácticas de versionado.
La versión debe mantenerse consistente en:
la app
archivos del proyecto
README
Git tags o releases
GitHub Actions
Cada cambio relevante debe reflejarse en una nueva versión.

Logging
Incluir un archivo log.txt con timestamp.
Aplicar buenas prácticas de logging:
timestamps claros
niveles (INFO, WARNING, ERROR)
manejo de excepciones
mensajes legibles
El log debe almacenarse en una ubicación segura y predecible.
Evitar exponer datos sensibles en logs.

Seguridad
Aplicar mejores prácticas para evitar vulnerabilidades.
Validar entradas.
Manejar errores sin filtrar información sensible.
No hardcodear credenciales.
Si hay contraseñas, tratarlas de forma segura.
Minimizar riesgos comunes de seguridad en aplicaciones desktop.
Si alguna dependencia o diseño propuesto puede ser inseguro, indícalo y propone alternativa.

Ejecución y experiencia de usuario
Si hay procesos largos, usar hilos, tareas en background o mecanismos seguros para que la GUI siga respondiendo.
No mostrar ventanas de consola/cmd si el programa es de escritorio.
Todo lo que pueda hacerse en modo silencioso debe ejecutarse en modo silencioso.
Mantener una experiencia fluida y profesional.

Calidad del código
Aplicar mejores prácticas de programación.
Escribir código claro, modular, mantenible y documentado.
Incluir comentarios útiles solo donde aporten valor.
Evitar duplicación de lógica.
Proponer mejoras reales del proyecto actual.
Si el proyecto ya funciona, analizar qué puede mejorarse sin romper compatibilidad.

About
En la barra de menús incluir una opción About que muestre:
{nombre_del_proyecto} {version}
Creado por Synyster Rick
{año} Derechos Reservados

Entregables esperados al generar o mejorar el proyecto
Cuando trabajes sobre mi proyecto, necesito que entregues:

Análisis inicial
Qué hace el proyecto actualmente
Qué se puede mejorar
Qué riesgos hay
Qué no debe tocarse para no romper funcionalidades

Plan de mejora
Lista clara de mejoras propuestas
Qué impacto tiene cada cambio
Cómo se conserva compatibilidad con versiones anteriores

Código completo
No quiero fragmentos sueltos
Quiero archivos completos y funcionales
Manteniendo todas las funciones previas

Estructura recomendada del proyecto
Proponer estructura de carpetas y módulos
Manejo de configuración
Código para config.json
Valores por defecto

Autoguardado
Sistema de logging
Código completo del log con mejores prácticas
Empaquetado
Preparar el proyecto para compilar a .exe
El .exe debe quedar en la misma carpeta donde está el .py
Debe usar el archivo .ico contenido en la misma carpeta
No abrir consola adicional en apps GUI
Internacionalización

Preparar soporte para varios idiomas en la GUI
Estructura escalable para agregar más idiomas luego

Forma de trabajar
Si te comparto código existente:
primero analízalo
luego propón mejoras
después entrega el código actualizado completo
No reemplaces cosas arbitrariamente.
Conserva compatibilidad.
Si hay dudas importantes, haz preguntas concretas antes de modificar partes críticas.
Si puedes inferir una solución razonable sin preguntarme, hazlo y explica tu decisión.
Comenta cada parte del código para saber qué hace.
PROMPT
        ),
        'github_release_master' => array(
            'title' => 'Prompt maestro para GitHub, versionado y releases',
            'summary' => 'Prepara GitHub, tags, README, licencia Apache 2.0, workflow y release automático.',
            'prompt' => <<<'PROMPT'
Actúa como un ingeniero senior DevOps y release manager.
Necesito que prepares mi proyecto Python para GitHub con versionado y release automáticos.

Objetivo
Crear y dejar documentado el flujo completo para subir mi proyecto a GitHub con buenas prácticas, versionado consistente y release automático.

Requisitos obligatorios
Repositorio
Crear un repositorio público en GitHub con el nombre del proyecto
Usar la rama main
Asumir que ya estoy autenticado por GitHub CLI

Commits
Crear el primer commit con un mensaje claro y profesional según lo que se haya implementado
Usar mensajes de commit legibles y alineados con versionado

Versionado
Manejar versión con formato Vx.x.x
Cada commit relevante debe generar una nueva versión
La versión debe coincidir en:
código fuente
GUI
README
tags
releases
workflow de GitHub

Documentación
Crear y completar con mejores prácticas:
README.md
descripción del proyecto
características
requisitos
dependencias
instrucciones de uso
cómo compilar
changelog si aplica
Definir licencia Apache License 2.0

Dependencias
Preparar correctamente:
requirements.txt
archivos auxiliares necesarios
documentación de instalación

Compilación
Compilar la aplicación a .exe
El ejecutable debe quedar en la misma carpeta del .py
Debe usar el .ico ubicado en la misma carpeta
La app GUI debe compilarse sin mostrar consola

GitHub Actions
Generar un workflow YAML para que en cada push a main:
se valide el proyecto
se construya la app
se genere un release automáticamente
se publique la versión correspondiente

El release debe usar la misma versión definida en la app

Aprendizaje paso a paso
Déjame los comandos paso a paso que usaste para que yo aprenda a hacerlo manualmente
Explica brevemente qué hace cada comando

Entregables esperados
Comandos completos de Git y GitHub CLI paso a paso
README.md completo
requirements.txt
licencia Apache 2.0
workflow YAML para release automático
instrucciones para compilar el .exe
estrategia de versionado recomendada
Comenta cada parte del código y de los archivos generados para saber qué hace.
PROMPT
        ),
        'daily_short_master' => array(
            'title' => 'Versión corta para uso diario',
            'summary' => 'Prompt condensado para pegar rápido en Codex sin perder el alcance principal.',
            'prompt' => <<<'PROMPT'
Actúa como un ingeniero senior Python + GUI + DevOps. Analiza mi proyecto actual y mejóralo sin perder ninguna funcionalidad existente. Quiero arquitectura separada entre frontend y backend, GUI moderna, no bloqueante, con botón Salir, barra de estado, countdown visible para autocierre, soporte multiidioma, config.json autoguardado, log.txt con timestamp y buenas prácticas, versión visible en GUI y versionado consistente. La app debe recordar posición y tamaño de ventana, permitir auto inicio y autocierre configurable, evitar messagebox innecesarios, no mostrar consola en apps GUI, incluir About con “{version} creado por Synyster Rick, {año} Derechos Reservados”, aplicar buenas prácticas de seguridad, empaquetarse a .exe con icono local, y preparar repositorio GitHub con README, licencia Apache 2.0, requirements, versionado Vx.x.x, commits claros y GitHub Actions para release automático en cada push a main. Entrega análisis, plan de mejora, estructura de proyecto, código completo y comandos paso a paso. Comenta cada parte del código para saber qué hace.
PROMPT
        ),
        'workflow_three_phases' => array(
            'title' => 'Recomendación: trabajar en 3 fases',
            'summary' => 'Secuencia recomendada para que el prompt maestro funcione mejor en iteraciones.',
            'prompt' => <<<'PROMPT'
Recomendación para que el prompt funcione mejor:

Úsalo en 3 fases en vez de pedir todo de una sola vez:

Fase 1
Analiza este proyecto y dime qué mejorarías sin romper nada.

Fase 2
Ahora refactorízalo con arquitectura limpia, config, logging, versionado y GUI no bloqueante.

Fase 3
Ahora prepara GitHub, README, licencia, compilación y workflow de release.

En cada fase, comenta cada parte del código para saber qué hace.
PROMPT
        ),
    );
}

/**
 * Devuelve una version reducida del catalogo para UI o API.
 */
function php_llm_get_prompt_preset_summaries($presets)
{
    $summaries = array();

    foreach ($presets as $key => $preset) {
        $summaries[$key] = array(
            'title' => isset($preset['title']) ? $preset['title'] : $key,
            'summary' => isset($preset['summary']) ? $preset['summary'] : '',
        );
    }

    return $summaries;
}

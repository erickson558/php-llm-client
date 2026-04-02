<?php

/**
 * Metadatos centrales del proyecto.
 *
 * Este archivo evita que el nombre, la versión y el autor
 * queden duplicados en varios lugares de la aplicación.
 */
function php_llm_get_project_meta()
{
    return array(
        'name' => 'PHP LLM Client',
        'slug' => 'php-llm-client',
        'version' => 'V0.2.3',
        'author' => 'Synyster Rick',
        'year' => gmdate('Y'),
        'description' => 'Cliente PHP con soporte para OpenAI, DeepSeek, Anthropic y endpoints compatibles con OpenAI.',
    );
}

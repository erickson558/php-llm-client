<?php

/**
 * Catalogo de proveedores con URLs oficiales y enlaces a su documentacion.
 *
 * Estas URLs se usan para:
 * - autocompletar la Base URL en la interfaz
 * - mostrar enlaces a la documentacion oficial
 * - servir como fallback en el cliente PHP si el usuario no define un base_url
 */
function php_llm_get_provider_catalog()
{
    return array(
        'openai' => array(
            'type' => 'openai_compatible',
            'official_base_url' => 'https://api.openai.com/v1',
            'docs_url' => 'https://platform.openai.com/docs/api-reference/chat/create-chat-completion',
            'notes' => 'OpenAI Chat Completions usa POST /v1/chat/completions.',
        ),
        'deepseek' => array(
            'type' => 'openai_compatible',
            'official_base_url' => 'https://api.deepseek.com',
            'docs_url' => 'https://api-docs.deepseek.com/',
            'notes' => 'DeepSeek usa formato compatible con OpenAI. Tambien acepta https://api.deepseek.com/v1 como base_url.',
        ),
        'anthropic' => array(
            'type' => 'anthropic',
            'official_base_url' => 'https://api.anthropic.com/v1',
            'docs_url' => 'https://docs.anthropic.com/en/api/messages-examples',
            'notes' => 'Anthropic Messages API usa POST /v1/messages y requiere el header anthropic-version.',
        ),
        'custom' => array(
            'type' => 'openai_compatible',
            'official_base_url' => '',
            'docs_url' => '',
            'notes' => 'Proveedor manual para gateways propios o servicios compatibles con OpenAI.',
        ),
    );
}

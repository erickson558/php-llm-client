<?php

return array(
    'default_provider' => 'openai',
    'providers' => array(
        'openai' => array(
            'type' => 'openai_compatible',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => '',
            'default_model' => 'gpt-4.1-mini',
        ),
        'deepseek' => array(
            'type' => 'openai_compatible',
            'base_url' => 'https://api.deepseek.com',
            'api_key' => '',
            'default_model' => 'deepseek-chat',
        ),
        'anthropic' => array(
            'type' => 'anthropic',
            'base_url' => 'https://api.anthropic.com/v1',
            'api_key' => '',
            'default_model' => 'claude-sonnet-4-5',
            'anthropic_version' => '2023-06-01',
        ),
        'custom' => array(
            'type' => 'openai_compatible',
            'base_url' => 'https://tu-endpoint-openai-compatible/v1',
            'api_key' => '',
            'default_model' => 'tu-modelo',
        ),
    ),
);

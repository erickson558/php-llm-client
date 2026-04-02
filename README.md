# PHP LLM Client

Versión actual: `V0.2.1`

Cliente web en PHP para consultar modelos de lenguaje desde una sola interfaz, con soporte para:

- OpenAI
- DeepSeek
- Anthropic
- endpoints compatibles con OpenAI

## Qué hace el programa

Esta aplicación te permite:

- seleccionar un proveedor y autocompletar su Base URL oficial
- definir modelo, API key y parámetros básicos
- enviar `system + prompt` o un arreglo `messages_json`
- consumir el backend desde navegador o por `POST` JSON
- alternar entre modo claro y modo oscuro con persistencia local y contraste más claro en el tema light
- usar una interfaz inspirada en una armadura de Iron Man con estilo HUD

## Estado actual del proyecto

El proyecto está pensado para el entorno actual de EasyPHP y PHP 5.4, por eso evita Composer y dependencias modernas obligatorias.

## Características principales

- interfaz futurista con efectos visuales y animaciones suaves
- tematización tipo Iron Man con reactor visual y paleta blindada rojo/oro
- modo claro y modo oscuro persistido en `localStorage` con cambio de tema más robusto
- cockpit reorganizado por pasos para separar conexión, ajustes y contenido
- activación visual del modo `messages_json` para evitar mezclarlo con `System + Prompt`
- catálogo central de proveedores oficiales
- cliente backend con adaptadores para `anthropic` y `openai_compatible`
- transporte HTTP con fallback a `curl.exe` en Windows
- versión visible en la GUI
- endpoint API reutilizable

## Estructura del proyecto

```text
php-llm/
├─ .github/workflows/release.yml
├─ .gitignore
├─ api.php
├─ CHANGELOG.md
├─ config.example.php
├─ index.php
├─ LICENSE
├─ project_meta.php
├─ provider_catalog.php
├─ README.md
├─ VERSION
└─ lib/
   ├─ HttpTransport.php
   └─ LlmClient.php
```

## Dependencias

Dependencias de ejecución:

- PHP 5.4 o superior
- `json`
- `mbstring` recomendado
- `curl.exe` disponible en Windows si PHP no tiene `curl` o `https`

Dependencias para GitHub Actions:

- PHP 8.3 para lint en CI
- `zip` en el runner de GitHub

## Instalación local

1. Copia la carpeta `php-llm` dentro de tu servidor web.
2. Duplica `config.example.php` como `config.php`.
3. Agrega tus API keys y modelos por defecto.
4. Abre `index.php` desde el navegador.

## Configuración

Archivo: `config.php`

Ejemplo:

```php
<?php
return array(
    'default_provider' => 'openai',
    'providers' => array(
        'openai' => array(
            'type' => 'openai_compatible',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'TU_API_KEY',
            'default_model' => 'gpt-4.1-mini',
        ),
    ),
);
```

## Uso web

Abre:

`/php-llm/index.php`

## Uso API

`GET /php-llm/api.php`

Devuelve metadatos del proyecto y proveedores disponibles.

`POST /php-llm/api.php`

Ejemplo:

```json
{
  "provider": "deepseek",
  "model": "deepseek-chat",
  "system": "Eres un asistente útil.",
  "prompt": "Explica qué hace este proyecto.",
  "temperature": 0.2,
  "max_tokens": 600
}
```

## Versionado

Formato usado:

- `Vx.y.z`

Regla recomendada:

- `V0.1.0`: primera versión pública usable
- `V0.1.1`: correcciones pequeñas
- `V0.2.0`: mejoras compatibles de funcionalidad
- `V1.0.0`: versión estable con interfaz y flujo consolidados

La versión debe mantenerse sincronizada en:

- `VERSION`
- `project_meta.php`
- `README.md`
- `CHANGELOG.md`
- tags de Git
- releases de GitHub

## Cómo publicar una nueva versión

1. Actualiza `VERSION`.
2. Actualiza `project_meta.php`.
3. Actualiza `CHANGELOG.md`.
4. Haz commit.
5. Push a `main`.
6. GitHub Actions generará el release.

## GitHub Actions

El workflow hace lo siguiente en cada push a `main`:

- valida el formato de `VERSION`
- valida que `VERSION`, `project_meta.php`, `README.md` y `CHANGELOG.md` estén sincronizados
- ejecuta lint de todos los archivos PHP
- crea un `.zip` del proyecto
- crea el tag si no existe
- publica o actualiza el release correspondiente

## Seguridad

- no subas `config.php`
- no hardcodees API keys en archivos públicos
- evita logs con datos sensibles
- en Windows, el fallback por `curl.exe` usa `ssl-no-revoke` para evitar fallos frecuentes de Schannel en algunos entornos

## Licencia

Apache License 2.0

Archivo incluido:

- `LICENSE`

## Referencias oficiales usadas

- OpenAI Chat Completions: https://platform.openai.com/docs/api-reference/chat/create-chat-completion
- Anthropic Messages API: https://docs.anthropic.com/en/api/messages-examples
- DeepSeek API Docs: https://api-docs.deepseek.com/

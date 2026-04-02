<?php
require_once dirname(__FILE__) . '/lib/LlmClient.php';
require_once dirname(__FILE__) . '/provider_catalog.php';
require_once dirname(__FILE__) . '/project_meta.php';

// Carga configuración local o usa defaults del proyecto.
$configPath = dirname(__FILE__) . '/config.php';
$configExists = file_exists($configPath);
$config = $configExists ? require $configPath : require dirname(__FILE__) . '/config.example.php';
$projectMeta = php_llm_get_project_meta();
$providerCatalog = php_llm_get_provider_catalog();
$config = php_llm_merge_config_with_catalog($config, $providerCatalog);
$providerUiCatalog = php_llm_build_provider_ui_catalog($config, $providerCatalog);

$defaultProvider = isset($config['default_provider']) ? $config['default_provider'] : key($config['providers']);
$response = null;
$error = '';
$form = array(
    'provider' => $defaultProvider,
    'base_url' => '',
    'model' => '',
    'api_key' => '',
    'system' => 'Eres un asistente útil y directo.',
    'prompt' => 'Explica brevemente cómo funciona este cliente PHP.',
    'messages_json' => '',
    'temperature' => '0.2',
    'max_tokens' => '800',
    'timeout' => '120',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $defaultValue) {
        if (isset($_POST[$key])) {
            $form[$key] = trim($_POST[$key]);
        }
    }

    try {
        $client = new LlmClient($config, null);
        $request = build_request_from_form($form, $providerUiCatalog);
        $response = $client->chat($request);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
} else {
    $providerDefaults = php_llm_get_provider_defaults($defaultProvider, $providerUiCatalog);
    $form['base_url'] = isset($providerDefaults['base_url']) ? $providerDefaults['base_url'] : '';
    $form['model'] = isset($providerDefaults['default_model']) ? $providerDefaults['default_model'] : '';
}

// Construye la solicitud final que se enviará al backend.
function build_request_from_form($form, $providerUiCatalog)
{
    $providerDefaults = php_llm_get_provider_defaults($form['provider'], $providerUiCatalog);
    $request = array(
        'provider' => $form['provider'],
        'temperature' => $form['temperature'],
        'max_tokens' => $form['max_tokens'],
        'timeout' => $form['timeout'],
    );

    $resolvedBaseUrl = $form['base_url'] !== '' ? $form['base_url'] : (isset($providerDefaults['base_url']) ? $providerDefaults['base_url'] : '');
    $resolvedModel = $form['model'] !== '' ? $form['model'] : (isset($providerDefaults['default_model']) ? $providerDefaults['default_model'] : '');

    if ($resolvedBaseUrl !== '') {
        $request['base_url'] = $resolvedBaseUrl;
    }
    if ($resolvedModel !== '') {
        $request['model'] = $resolvedModel;
    }
    if ($form['api_key'] !== '') {
        $request['api_key'] = $form['api_key'];
    }

    if ($form['messages_json'] !== '') {
        $request['messages_json'] = $form['messages_json'];
    } else {
        $request['system'] = $form['system'];
        $request['prompt'] = $form['prompt'];
    }

    return $request;
}

// Devuelve la entrada del catálogo lista para la UI.
function php_llm_get_provider_defaults($providerName, $providerUiCatalog)
{
    if (isset($providerUiCatalog[$providerName]) && is_array($providerUiCatalog[$providerName])) {
        return $providerUiCatalog[$providerName];
    }
    return array();
}

// Mezcla providers configurados localmente con el catálogo oficial.
function php_llm_merge_config_with_catalog($config, $providerCatalog)
{
    if (!isset($config['providers']) || !is_array($config['providers'])) {
        $config['providers'] = array();
    }
    foreach ($providerCatalog as $providerName => $providerMeta) {
        if (!isset($config['providers'][$providerName])) {
            $config['providers'][$providerName] = array();
        }
        if (!isset($config['providers'][$providerName]['type']) && isset($providerMeta['type'])) {
            $config['providers'][$providerName]['type'] = $providerMeta['type'];
        }
        if (!isset($config['providers'][$providerName]['base_url']) && isset($providerMeta['official_base_url'])) {
            $config['providers'][$providerName]['base_url'] = $providerMeta['official_base_url'];
        }
    }
    if (!isset($config['default_provider']) || trim((string) $config['default_provider']) === '') {
        $config['default_provider'] = 'openai';
    }
    return $config;
}

// Prepara el catálogo que la GUI consumirá desde JavaScript.
function php_llm_build_provider_ui_catalog($config, $providerCatalog)
{
    $uiCatalog = array();
    foreach ($config['providers'] as $providerName => $providerConfig) {
        $meta = isset($providerCatalog[$providerName]) ? $providerCatalog[$providerName] : array();
        $uiCatalog[$providerName] = array(
            'base_url' => isset($meta['official_base_url']) && trim($meta['official_base_url']) !== '' ? $meta['official_base_url'] : (isset($providerConfig['base_url']) ? $providerConfig['base_url'] : ''),
            'default_model' => isset($providerConfig['default_model']) ? $providerConfig['default_model'] : '',
            'docs_url' => isset($meta['docs_url']) ? $meta['docs_url'] : '',
            'notes' => isset($meta['notes']) ? $meta['notes'] : '',
        );
    }
    return $uiCatalog;
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function pretty_json($value)
{
    $flags = 0;
    if (defined('JSON_PRETTY_PRINT')) { $flags |= JSON_PRETTY_PRINT; }
    if (defined('JSON_UNESCAPED_UNICODE')) { $flags |= JSON_UNESCAPED_UNICODE; }
    if (defined('JSON_UNESCAPED_SLASHES')) { $flags |= JSON_UNESCAPED_SLASHES; }
    return json_encode($value, $flags);
}
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?php echo h($projectMeta['name']); ?> <?php echo h($projectMeta['version']); ?></title>
    <style type="text/css">
        :root{--bg:#130709;--bg2:#2b0f12;--card:rgba(30,10,14,.78);--card2:rgba(39,13,16,.94);--text:#fff4eb;--muted:#d7b6a8;--accent:#7de9ff;--accent2:#ffcc62;--accent3:#d73d2f;--border:rgba(255,196,104,.16);--input:rgba(35,12,16,.84);--shadow:0 22px 55px rgba(0,0,0,.42);--ok:rgba(25,121,88,.22);--info:rgba(0,138,182,.18);--bad:rgba(160,22,47,.28);--code:#15070a}
        body.light{--bg:#f8ece3;--bg2:#fff7f1;--card:rgba(255,249,244,.84);--card2:rgba(255,252,248,.96);--text:#411518;--muted:#8f5f56;--accent:#0a9fc7;--accent2:#c48816;--accent3:#c13b2f;--border:rgba(196,136,22,.18);--input:rgba(255,251,247,.95);--shadow:0 16px 40px rgba(122,58,33,.12);--ok:rgba(16,108,77,.1);--info:rgba(10,140,184,.12);--bad:rgba(160,22,47,.1)}
        *{box-sizing:border-box}body{margin:0;font-family:Segoe UI,Tahoma,sans-serif;color:var(--text);background:radial-gradient(circle at 18% 18%,rgba(125,233,255,.13),transparent 19%),radial-gradient(circle at 84% 12%,rgba(215,61,47,.2),transparent 22%),radial-gradient(circle at 78% 78%,rgba(255,204,98,.14),transparent 18%),linear-gradient(145deg,var(--bg),var(--bg2));min-height:100vh;overflow-x:hidden;transition:background .35s ease,color .35s ease}
        body:before,body:after{content:"";position:fixed;inset:0;pointer-events:none}body:before{background-image:linear-gradient(rgba(255,214,162,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,214,162,.03) 1px,transparent 1px);background-size:30px 30px;opacity:.4;mask-image:radial-gradient(circle at center,black,transparent 85%)}body:after{height:150px;top:-180px;left:-10%;right:-10%;background:linear-gradient(180deg,transparent 0%,rgba(125,233,255,.14) 50%,transparent 100%);filter:blur(18px);animation:scanMove 8s linear infinite}
        .shell{max-width:1260px;margin:0 auto;padding:28px 18px 42px;position:relative}
        .shell:before,.shell:after{content:"";position:fixed;pointer-events:none;border-radius:999px;filter:blur(12px);opacity:.5}.shell:before{width:260px;height:260px;left:-70px;top:120px;background:radial-gradient(circle,rgba(125,233,255,.28),transparent 70%)}.shell:after{width:300px;height:300px;right:-90px;top:58%;background:radial-gradient(circle,rgba(215,61,47,.22),transparent 72%)}
        .hero,.panel,.side{position:relative;overflow:hidden;background:var(--card);border:1px solid var(--border);box-shadow:var(--shadow);backdrop-filter:blur(18px)}
        .hero{border-radius:30px;padding:28px;margin-bottom:22px;background:linear-gradient(135deg,rgba(88,17,20,.85),rgba(34,9,14,.92));clip-path:polygon(0 0,97% 0,100% 12%,100% 100%,3% 100%,0 88%)}body.light .hero{background:linear-gradient(135deg,rgba(255,247,239,.92),rgba(255,251,248,.98))}.hero:before,.panel:before,.side:before{content:"";position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.06),transparent 40%);pointer-events:none}
        .hero:after{content:"";position:absolute;inset:16px;border:1px solid rgba(255,204,98,.18);border-radius:24px;clip-path:polygon(0 0,98% 0,100% 11%,100% 100%,2% 100%,0 89%)}
        .hero-top,.hero-grid,.layout,.stats,.grid,.response-meta,.row{display:grid;gap:16px}.hero-top{grid-template-columns:1fr auto;align-items:center}.badges{display:flex;flex-wrap:wrap;gap:10px}.badge,.chip{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;border:1px solid var(--border);background:rgba(255,255,255,.05);font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.08em}
        .dot{width:10px;height:10px;border-radius:999px;background:var(--accent);box-shadow:0 0 18px rgba(125,233,255,.75);animation:pulseDot 2s ease-in-out infinite}
        .theme-btn,.button{display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;color:var(--text);font-weight:700;border:1px solid var(--border);transition:transform .2s ease,box-shadow .2s ease,background .2s ease}
        .theme-btn{border-radius:999px;min-height:46px;padding:0 16px;background:linear-gradient(135deg,rgba(255,204,98,.18),rgba(125,233,255,.09))}.theme-btn:hover,.button:hover{transform:translateY(-2px);box-shadow:0 10px 22px rgba(255,204,98,.16)}
        .hero-grid{grid-template-columns:1.2fr .8fr}.hero h1{margin:0 0 10px;font-size:42px;line-height:1.02;letter-spacing:-.03em;text-transform:uppercase;text-shadow:0 0 22px rgba(125,233,255,.18)}.hero p{margin:0;color:var(--muted);line-height:1.7}
        .stats{grid-template-columns:repeat(2,minmax(0,1fr))}.stat{border:1px solid var(--border);border-radius:20px;padding:16px;background:linear-gradient(145deg,rgba(255,255,255,.05),rgba(255,255,255,.02));transition:transform .24s ease,border-color .24s ease,box-shadow .24s ease;position:relative;overflow:hidden}.stat:before{content:"";position:absolute;left:-20%;top:0;bottom:0;width:60%;background:linear-gradient(90deg,transparent,rgba(255,204,98,.08),transparent);transform:skewX(-25deg)}.stat:hover{transform:translateY(-4px);border-color:rgba(255,204,98,.35);box-shadow:0 14px 28px rgba(0,0,0,.18)}.stat .k{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}.stat .v{margin-top:10px;font-size:24px;font-weight:700;color:var(--accent2)}.stat .d{margin-top:8px;color:var(--muted);font-size:13px;line-height:1.6}
        .layout{grid-template-columns:1.45fr .9fr;align-items:start}.panel{border-radius:28px;padding:24px;background:linear-gradient(160deg,rgba(57,16,19,.84),rgba(22,8,11,.94));clip-path:polygon(0 0,98% 0,100% 10%,100% 100%,0 100%,0 12%)}body.light .panel{background:linear-gradient(160deg,rgba(255,249,244,.92),rgba(255,252,248,.98))}.title{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px}.title h2,.title h3{margin:0}.caption,.helper,.side p,.side li,.footer{color:var(--muted);font-size:13px;line-height:1.7}
        .notice{padding:14px 16px;border-radius:18px;border:1px solid var(--border);margin:0 0 14px}.notice.info{background:var(--info)}.notice.ok{background:var(--ok)}.notice.bad{background:var(--bad)}
        .grid{grid-template-columns:repeat(12,minmax(0,1fr));gap:14px}.field{grid-column:span 12}.s2{grid-column:span 2}.s3{grid-column:span 3}.s5{grid-column:span 5}.s6{grid-column:span 6}.field label{display:block;margin-bottom:8px;font-weight:700;font-size:13px}
        input[type=text],input[type=password],select,textarea{width:100%;border:1px solid var(--border);background:var(--input);color:var(--text);border-radius:18px;padding:13px 14px;font-size:14px;outline:none;transition:border-color .2s ease,box-shadow .2s ease,transform .2s ease;box-shadow:inset 0 0 0 1px rgba(255,255,255,.02)}
        input[type=text]:focus,input[type=password]:focus,select:focus,textarea:focus{border-color:rgba(125,233,255,.55);box-shadow:0 0 0 4px rgba(125,233,255,.1), inset 0 0 20px rgba(255,204,98,.04);transform:translateY(-1px)}
        textarea{min-height:165px;resize:vertical;line-height:1.6}.summary,.micro,.side{border-radius:22px;padding:18px;border:1px solid var(--border);background:var(--card2)}.summary,.micro{border-radius:18px;padding:14px 16px;background:rgba(255,255,255,.04)}.row{grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-top:10px}
        .button{border-radius:16px;min-height:48px;padding:0 18px;background:linear-gradient(135deg,rgba(215,61,47,.38),rgba(255,204,98,.22));text-transform:uppercase;letter-spacing:.04em}.button.alt{background:linear-gradient(135deg,rgba(125,233,255,.15),rgba(255,204,98,.12))}.button.ghost{background:transparent}
        .stack{display:grid;gap:18px}.side{background:linear-gradient(160deg,rgba(50,14,17,.86),rgba(20,8,11,.95))}body.light .side{background:linear-gradient(160deg,rgba(255,248,242,.94),rgba(255,252,248,.98))}.side h3{margin:0 0 8px;color:var(--accent2);text-transform:uppercase;letter-spacing:.05em}.side ul{margin:10px 0 0 18px;padding:0}.link{display:inline-flex;align-items:center;color:var(--accent);text-decoration:none;font-weight:700}.link:hover{text-decoration:underline}
        .response pre{margin:0;background:var(--code);border:1px solid rgba(255,255,255,.08);color:#dce7ff;padding:18px;border-radius:18px;overflow:auto;font-size:12px;line-height:1.65;white-space:pre-wrap;word-wrap:break-word}.response-meta{grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin:0 0 14px}.chip{justify-content:center}
        .line{height:1px;margin:18px 0;background:linear-gradient(90deg,transparent,rgba(255,204,98,.46),transparent)}.footer{text-align:center;margin-top:18px;letter-spacing:.06em;text-transform:uppercase}
        .armor-subtitle{display:inline-flex;align-items:center;gap:10px;margin-bottom:12px;color:var(--accent2);font-size:12px;font-weight:700;letter-spacing:.18em;text-transform:uppercase}.armor-subtitle:before,.armor-subtitle:after{content:"";display:block;width:36px;height:1px;background:linear-gradient(90deg,transparent,var(--accent2))}
        .arc-reactor{position:absolute;right:34px;top:84px;width:118px;height:118px;border-radius:999px;border:1px solid rgba(125,233,255,.4);box-shadow:0 0 0 8px rgba(125,233,255,.05),0 0 35px rgba(125,233,255,.22),inset 0 0 25px rgba(125,233,255,.18);background:radial-gradient(circle,rgba(125,233,255,.82) 0%,rgba(125,233,255,.22) 34%,rgba(0,0,0,.08) 55%,transparent 72%);animation:reactorSpin 12s linear infinite}
        .arc-reactor:before,.arc-reactor:after{content:"";position:absolute;inset:11px;border-radius:999px;border:1px dashed rgba(255,204,98,.3)}.arc-reactor:after{inset:26px;border-style:solid;border-color:rgba(125,233,255,.45);box-shadow:0 0 18px rgba(125,233,255,.3)}
        .hero-corners span,.panel-corners span{position:absolute;width:26px;height:26px;border-color:rgba(255,204,98,.58);pointer-events:none}.hero-corners .tl,.panel-corners .tl{top:16px;left:16px;border-top:2px solid;border-left:2px solid}.hero-corners .tr,.panel-corners .tr{top:16px;right:16px;border-top:2px solid;border-right:2px solid}.hero-corners .bl,.panel-corners .bl{bottom:16px;left:16px;border-bottom:2px solid;border-left:2px solid}.hero-corners .br,.panel-corners .br{bottom:16px;right:16px;border-bottom:2px solid;border-right:2px solid}
        @keyframes pulseDot{0%,100%{transform:scale(1);opacity:.85}50%{transform:scale(1.35);opacity:1}}@keyframes scanMove{0%{transform:translateY(0)}100%{transform:translateY(120vh)}}@keyframes reactorSpin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}
        @media (max-width:1100px){.hero-grid,.layout{grid-template-columns:1fr}}
        @media (max-width:760px){.shell{padding:18px 12px 28px}.hero{padding:20px;border-radius:22px;clip-path:none}.panel{clip-path:none}.hero h1{font-size:30px}.grid{grid-template-columns:1fr}.s2,.s3,.s5,.s6{grid-column:span 12}.stats{grid-template-columns:1fr}.hero-top{grid-template-columns:1fr}.arc-reactor{position:relative;right:auto;top:auto;margin:10px auto 0}}
    </style>
</head>
<body>
<div class="shell">
    <section class="hero">
        <div class="hero-corners"><span class="tl"></span><span class="tr"></span><span class="bl"></span><span class="br"></span></div>
        <div class="arc-reactor"></div>
        <div class="hero-top">
            <div class="badges">
                <span class="badge"><span class="dot"></span>Stark HUD</span>
                <span class="badge"><?php echo h($projectMeta['version']); ?></span>
                <span class="badge"><?php echo h($projectMeta['author']); ?></span>
            </div>
            <button type="button" class="theme-btn" id="theme_toggle">Cambiar tema</button>
        </div>
        <div class="hero-grid">
            <div>
                <div class="armor-subtitle">Armor Interface Online</div>
                <h1><?php echo h($projectMeta['name']); ?></h1>
                <p><?php echo h($projectMeta['description']); ?> La interfaz ahora usa un lenguaje visual inspirado en una armadura de alta tecnología: metal rojo, acentos dorados, reactor azul, paneles angulares, HUD animado y Base URL automática por proveedor.</p>
            </div>
            <div class="stats">
                <div class="stat"><div class="k">Armor Nodes</div><div class="v"><?php echo h(count($providerUiCatalog)); ?></div><div class="d">OpenAI, DeepSeek, Anthropic y custom.</div></div>
                <div class="stat"><div class="k">Command Packs</div><div class="v"><?php echo h(count($promptPresetSummaries)); ?></div><div class="d">Biblioteca disponible desde GUI y API.</div></div>
                <div class="stat"><div class="k">Build</div><div class="v"><?php echo h($projectMeta['version']); ?></div><div class="d">Visible en la app para alinear Git y releases.</div></div>
                <div class="stat"><div class="k">Core Runtime</div><div class="v">PHP 5.4</div><div class="d">Diseñado para correr en tu EasyPHP actual.</div></div>
            </div>
        </div>
    </section>

    <div class="layout">
        <section class="panel">
            <div class="panel-corners"><span class="tl"></span><span class="tr"></span><span class="bl"></span><span class="br"></span></div>
            <div class="title"><h2>Armor Command Deck</h2><span class="badge">Auto URL + Theme Toggle</span></div>
            <div class="caption">Selecciona proveedor, usa la Base URL oficial y consulta tu modelo desde un panel de control con estilo HUD blindado.</div>
            <div class="line"></div>

            <div class="notice info">La <strong>Base URL</strong> cambia automáticamente al seleccionar un proveedor predefinido. Si usas un gateway o proxy propio, selecciona <strong>custom</strong> y escribe tu URL manualmente.</div>
            <?php if (!$configExists) { ?><div class="notice info">No existe <strong>config.php</strong>. Puedes usar <strong>config.example.php</strong> como plantilla o escribir la API key, Base URL y modelo directamente en este formulario.</div><?php } ?>
            <?php if ($error !== '') { ?><div class="notice bad"><?php echo h($error); ?></div><?php } ?>
            <?php if ($response !== null && $error === '') { ?><div class="notice ok">Solicitud enviada correctamente al proveedor <strong><?php echo h($response['provider']); ?></strong> usando el transporte <strong><?php echo h($response['transport']); ?></strong>.</div><?php } ?>

            <form method="post" action="">
                <div class="grid">
                    <div class="field s3">
                        <label for="provider">Proveedor</label>
                        <select name="provider" id="provider">
                            <?php foreach ($config['providers'] as $providerName => $providerConfig) { ?>
                                <option value="<?php echo h($providerName); ?>"<?php echo $form['provider'] === $providerName ? ' selected="selected"' : ''; ?>><?php echo h($providerName); ?></option>
                            <?php } ?>
                        </select>
                        <span class="helper" id="provider_notes"></span>
                    </div>
                    <div class="field s5">
                        <label for="base_url">Base URL</label>
                        <input type="text" name="base_url" id="base_url" value="<?php echo h($form['base_url']); ?>" />
                        <span class="helper"><a href="#" class="link" id="provider_docs_link" target="_blank">Ver documentación oficial</a></span>
                    </div>
                    <div class="field s2">
                        <label for="model">Modelo</label>
                        <input type="text" name="model" id="model" value="<?php echo h($form['model']); ?>" />
                    </div>
                    <div class="field s2">
                        <label for="api_key">API key</label>
                        <input type="password" name="api_key" id="api_key" value="" />
                        <span class="helper">Si lo dejas vacío, usa la de <code>config.php</code>.</span>
                    </div>

                    <div class="field s2">
                        <label for="temperature">Temperature</label>
                        <input type="text" name="temperature" id="temperature" value="<?php echo h($form['temperature']); ?>" />
                    </div>
                    <div class="field s2">
                        <label for="max_tokens">Max tokens</label>
                        <input type="text" name="max_tokens" id="max_tokens" value="<?php echo h($form['max_tokens']); ?>" />
                    </div>
                    <div class="field s2">
                        <label for="timeout">Timeout</label>
                        <input type="text" name="timeout" id="timeout" value="<?php echo h($form['timeout']); ?>" />
                        <span class="helper">Segundos máximos para la llamada HTTP.</span>
                    </div>

                    <div class="field s6">
                        <div class="micro"><strong>API JSON:</strong><div class="helper">También puedes consumir <code>api.php</code> por POST y enviar <code>provider</code>, <code>model</code>, <code>system</code>, <code>prompt</code> o <code>messages_json</code>.</div></div>
                    </div>

                    <div class="field s6">
                        <label for="system">System prompt</label>
                        <textarea name="system" id="system"><?php echo h($form['system']); ?></textarea>
                    </div>
                    <div class="field s6">
                        <label for="prompt">Prompt</label>
                        <textarea name="prompt" id="prompt"><?php echo h($form['prompt']); ?></textarea>
                    </div>
                    <div class="field">
                        <label for="messages_json">Messages JSON opcional</label>
                        <textarea name="messages_json" id="messages_json" placeholder='[{"role":"system","content":"Eres util"},{"role":"user","content":"Hola"}]'><?php echo h($form['messages_json']); ?></textarea>
                        <span class="helper">Si llenas este campo, se ignoran System y Prompt y se usa exactamente este arreglo de mensajes.</span>
                    </div>
                    <div class="field"><input type="submit" class="button" value="Consultar modelo" /></div>
                </div>
            </form>
        </section>

        <aside class="stack">
            <div class="side"><h3>Visual Mode</h3><p>La app recuerda tu tema usando <code>localStorage</code>. El botón superior alterna entre modo claro y oscuro sin recargar la página.</p></div>
            <div class="side"><h3>Armor Systems</h3><ul><li>Selecciona proveedor y autocompleta la Base URL oficial.</li><li>Adapta la solicitud a OpenAI-compatible o Anthropic.</li><li>Permite usar <code>system + prompt</code> o <code>messages_json</code>.</li><li>Expone una API JSON reutilizable.</li></ul></div>
            <div class="side"><h3>Build Version</h3><p>Versión activa: <strong><?php echo h($projectMeta['version']); ?></strong></p><p>Esta versión se usará también para Git, GitHub y releases automáticos.</p></div>
            <div class="side"><h3>About</h3><p><?php echo h($projectMeta['name']); ?> <?php echo h($projectMeta['version']); ?><br />Creado por <?php echo h($projectMeta['author']); ?><br /><?php echo h($projectMeta['year']); ?> Derechos Reservados</p></div>
        </aside>
    </div>

    <?php if ($response !== null) { ?>
        <section class="panel response" style="margin-top:22px;">
            <div class="title"><h2>Response Stream</h2><span class="badge">Decoded Output</span></div>
            <div class="response-meta">
                <span class="chip">proveedor=<?php echo h($response['provider']); ?></span>
                <span class="chip">modelo=<?php echo h($response['model']); ?></span>
                <span class="chip">transporte=<?php echo h($response['transport']); ?></span>
                <span class="chip">http=<?php echo h($response['status_code']); ?></span>
            </div>
            <?php if (isset($response['reasoning_text']) && trim($response['reasoning_text']) !== '') { ?><div class="title"><h3>Reasoning</h3></div><pre><?php echo h($response['reasoning_text']); ?></pre><div class="line"></div><?php } ?>
            <div class="title"><h3>Texto</h3></div><pre><?php echo h($response['text']); ?></pre>
            <div class="line"></div>
            <div class="title"><h3>Raw JSON</h3></div><pre><?php echo h(pretty_json($response['raw'])); ?></pre>
        </section>
    <?php } ?>

    <div class="footer"><?php echo h($projectMeta['name']); ?> · <?php echo h($projectMeta['version']); ?> · Built for EasyPHP</div>
</div>

<script type="text/javascript">
// Catálogos embebidos para la UI.
var providerCatalog = <?php echo pretty_json($providerUiCatalog); ?>;

// Referencias a controles del formulario.
var providerField = document.getElementById('provider');
var baseUrlField = document.getElementById('base_url');
var modelField = document.getElementById('model');
var docsLink = document.getElementById('provider_docs_link');
var providerNotes = document.getElementById('provider_notes');
var themeToggleButton = document.getElementById('theme_toggle');

// Refresca Base URL, modelo y ayuda del proveedor seleccionado.
function applyProviderSelection(shouldOverwriteFields) {
    var providerKey = providerField.value;
    var providerMeta = providerCatalog[providerKey] || {};
    if (shouldOverwriteFields) {
        if (providerMeta.base_url) { baseUrlField.value = providerMeta.base_url; }
        if (providerMeta.default_model) { modelField.value = providerMeta.default_model; }
    }
    if (providerMeta.docs_url) {
        docsLink.href = providerMeta.docs_url;
        docsLink.style.visibility = 'visible';
    } else {
        docsLink.href = '#';
        docsLink.style.visibility = 'hidden';
    }
    providerNotes.innerHTML = providerMeta.notes ? providerMeta.notes : 'Sin notas adicionales para este proveedor.';
}

// Aplica el tema indicado y lo persiste localmente.
function applyTheme(themeName) {
    document.body.className = themeName === 'light' ? 'light' : 'dark';
    if (window.localStorage) { window.localStorage.setItem('php-llm-theme', themeName === 'light' ? 'light' : 'dark'); }
    themeToggleButton.innerHTML = themeName === 'light' ? 'Modo oscuro' : 'Modo claro';
}

// Alterna entre modo claro y oscuro.
function toggleTheme() { applyTheme(document.body.className === 'light' ? 'dark' : 'light'); }

// Recupera el tema guardado previamente o usa oscuro por defecto.
function bootstrapTheme() {
    var storedTheme = 'dark';
    if (window.localStorage && window.localStorage.getItem('php-llm-theme')) { storedTheme = window.localStorage.getItem('php-llm-theme'); }
    applyTheme(storedTheme);
}

// Escape simple para imprimir HTML seguro en resúmenes generados desde JS.
function escapeHtml(value) {
    return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

providerField.onchange = function () { applyProviderSelection(true); };
themeToggleButton.onclick = function () { toggleTheme(); };

bootstrapTheme();
applyProviderSelection(false);
</script>
</body>
</html>

# Changelog

## V0.2.2

- compactación de la GUI para reducir espacio desperdiciado en desktop
- eliminación del modo `messages_json` y del bloque `Raw JSON` en la interfaz principal
- reorganización del formulario para usar mejor el ancho disponible con una columna lateral más corta
- documentación sincronizada con el nuevo flujo guiado de la cabina

## V0.2.1

- corrección del modo claro con cambio de tema más robusto basado en `data-theme`
- reorganización de la GUI en pasos claros: conexión, ajustes y contenido
- activación visual de modo raw para deshabilitar temporalmente `System` y `Prompt` cuando se usa `messages_json`
- endurecimiento del release automation para validar que `VERSION`, `project_meta.php`, `README.md` y `CHANGELOG.md` estén sincronizados

## V0.2.0

- interfaz retocada con temática inspirada en una armadura de Iron Man
- simplificación del flujo de entrada al eliminar la biblioteca de prompts maestro
- limpieza del endpoint `api.php` para exponer solo proveedores y ejemplo base de consumo
- corrección de metadata para evitar warnings de timezone en validaciones CLI
- documentación sincronizada con la versión, el flujo actual y el release automático

## V0.1.0

- creación del cliente PHP multi proveedor
- soporte para OpenAI, DeepSeek, Anthropic y proveedores compatibles con OpenAI
- interfaz web con modo claro y modo oscuro
- rediseño visual futurista con animaciones suaves
- biblioteca local de prompts maestro
- API JSON para integración externa
- catálogo oficial de proveedores con Base URL automática
- versionado centralizado con `VERSION` y `project_meta.php`
- preparación del proyecto para GitHub y releases automáticos

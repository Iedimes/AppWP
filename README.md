# Field Data - Gestión Agropecuaria via WhatsApp

## Archivos

- `index.html` - Interfaz de chat (web)
- `process.php` - API para el chat web
- `whatsapp.php` - Webhook para WhatsApp
- `config.php` - Configuración de API Keys

## Configuración

### 1. API Keys

Editar `config.php` y agregar tu key de Groq:

```php
$config = [
    'openai_api_key' => '',
    'groq_api_key' => 'TU_KEY_DE_GROQ'
];
```

### 2. WhatsApp con Twilio

1. Crear cuenta en [Twilio](https://www.twilio.com/)
2. Comprar un número de WhatsApp
3. Configurar el webhook:

```
URL: https://TU_DOMINIO/whatsapp.php?hub_verify_token=MI_TOKEN_DE_VERIFICACION
Method: POST
```

4. En Twilio, configurar:
   - **WhatsApp Sandbox**: https://{tu-dominio}/whatsapp.php

### 3. Hosting Recomendado

Para usar desde el teléfono con WhatsApp:

- **Railway** (gratis): railway.app
- **Render** (gratis): render.com
- **Vercel** (gratis): vercel.com (con PHP)
- **Hosting pago**: Hostinger, DonWeb, etc.

### 4. Commands (WhatsApp)

```
Sembramos 50 ha de maiz
Nacieron 2 terneros
Compre gasoil 150000
Compre 200 litros de gasoil
Hice 50 kms
cual es mi stock
cuanto gaste
ayuda
```

## Desarrollo Local

```bash
php -S localhost:8000
# Web: http://localhost:8000
# WhatsApp test: http://localhost:8000/whatsapp.php
```

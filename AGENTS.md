# Integration Alegra WooCommerce - Agents Guide

## Overview

Plugin de WordPress/WooCommerce que integra el sistema contable y de facturación [Alegra](https://www.alegra.com/) con WooCommerce. Permite crear y emitir facturas electrónicas colombianas (DIAN) directamente desde el panel de administración de WooCommerce.

- **Versión**: 0.0.15
- **PHP requerido**: >= 8.1
- **WordPress**: >= 6.0
- **WooCommerce**: >= 9.6
- **Licencia**: GPL v3.0
- **Autor**: Saúl Morales Pacheco

## Estructura del proyecto

```
integration-alegra-woo.php          # Punto de entrada del plugin
includes/
  class-integration-alegra-wc-plugin.php  # Bootstrapping, hooks, acciones WP/WC
  class-alegra-integration-wc.php         # Configuración WC_Integration (settings)
  class-integration-alegra-wc.php         # Lógica de negocio (facturas, clientes, productos)
  admin/
    settings.php                    # Campos de configuración principales (credenciales)
    other_settings.php              # Campos adicionales (vendedor, impuestos, centro de costo)
lib/
  src/Client.php                    # Cliente HTTP para la API de Alegra (Guzzle)
  vendor/                           # Dependencias del cliente Alegra
  plugin-update-checker/            # Auto-actualización desde GitHub releases
assets/js/
  field-dni-checkout.js             # Validación de DNI en checkout frontend
  integration-alegra.js             # Botón "Ver Factura" en admin (SweetAlert2)
  sweetalert2.min.js                # Librería SweetAlert2
tests/
  bootstrap.php                     # Bootstrap PHPUnit con WP test framework
  test-integration-alegra-wc.php    # Tests de calculate_dv
  test-invoice-generation.php       # Tests de generación de facturas
  test-client-management.php        # Tests de gestión de clientes
  wp-config.php                     # Config de BD para tests
```

## Arquitectura

### Flujo de inicialización

1. `integration-alegra-woo.php` → hook `plugins_loaded` → `integration_alegra_wc_smp_init()`
2. Verifica requisitos PHP >= 8.1
3. Instancia singleton `Integration_Alegra_WC_Plugin` → `run_alegra()` → `run()`
4. Carga autoloader, registra `WC_Alegra_Integration` como integración WC
5. Registra todos los hooks y filtros de WordPress/WooCommerce

### Clases principales

| Clase | Responsabilidad |
|-------|----------------|
| `Integration_Alegra_WC_Plugin` | Bootstrap, registro de hooks, enqueue de scripts, acciones bulk, campos checkout |
| `WC_Alegra_Integration` | Extiende `WC_Integration` — maneja la página de configuración en WooCommerce |
| `Integration_Alegra_WC` | Lógica estática de negocio: facturación, sincronización de productos, gestión de clientes |
| `Saulmoralespa\Alegra\Client` | Cliente HTTP para API REST de Alegra v1 |

### Flujo de facturación

1. Cambio de estado de pedido → `generate_invoice()` (hook `woocommerce_order_status_changed`)
2. Valida: integración habilitada, estado coincide con configuración, no existe factura previa
3. Obtiene DNI y tipo de documento del pedido (checkout blocks o clásico)
4. Busca o crea contacto en Alegra
5. Por cada item del pedido: busca o crea producto en Alegra por SKU
6. Si hay envío: crea item de servicio con SKU `S-P-W`
7. Crea factura con vendedor, centro de costo, impuestos configurados
8. Guarda `_invoice_id_alegra` como meta del pedido

### Emisión DIAN (timbrado)

- Acción bulk en listado de pedidos: "Emitir facturas Alegra"
- Máximo 10 facturas por lote (`MAX_INVOICES_TO_STAMP`)
- Llama a `stampInvoices()` de la API de Alegra
- Marca pedidos con `_invoice_emit_alegra` al confirmar

## API de Alegra

Base URL: `https://api.alegra.com/api/v1/`
Autenticación: HTTP Basic Auth (email + token)

### Endpoints utilizados

| Método | Endpoint | Uso |
|--------|----------|-----|
| GET | `/invoices/{id}` | Obtener factura / PDF |
| POST | `/invoices` | Crear factura |
| POST | `/invoices/stamp` | Timbrar facturas (DIAN) |
| GET | `/contacts` | Buscar contacto por identificación |
| POST | `/contacts` | Crear contacto |
| GET | `/items` | Buscar producto por referencia/SKU |
| POST | `/items` | Crear producto |
| PUT | `/items/{id}` | Editar producto |
| GET | `/sellers` | Listar vendedores |
| GET | `/cost-centers` | Listar centros de costo |
| GET | `/taxes` | Listar impuestos |

## Campos de checkout

El plugin registra campos adicionales en el checkout de WooCommerce:

- **Tipo de documento** (`document/type_document`): select con opciones CC, NIT, CE, DIE, TE, PP, TI, RC, FOREIGN_NIT
- **Número de documento** (`document/dni`): campo numérico, patrón `[0-9]{5,12}`

Compatible con checkout clásico y checkout por bloques de WooCommerce.

### Meta keys del pedido

- `_billing_type_document` / `_shipping_type_document`
- `_billing_dni` / `_shipping_dni`
- `_invoice_id_alegra` — ID de factura en Alegra
- `_invoice_emit_alegra` — Flag de factura timbrada en DIAN

## Configuración del plugin

Ruta admin: **WooCommerce → Ajustes → Integración → Integration Alegra Woocommerce**

### Credenciales

- `enabled`: Activar/Desactivar integración
- `debug`: Modo depuración (logs en WooCommerce → Estado)
- `user`: Email de cuenta Alegra
- `token`: Token API de Alegra (se valida contra la API al guardar)

### Facturación

- `order_status_generate_invoice`: Estado del pedido que dispara la factura
- `status_generate_invoice`: Estado de la factura en Alegra (borrador/abierto)
- `seller_generate_invoice`: Vendedor asociado (requerido)
- `cost_center_generate_invoice`: Centro de costo (opcional)
- `tax`: IVA aplicado a productos
- `shipping_tax`: IVA aplicado al envío

### Clientes y productos

- `allow_create_clients`: Crear clientes automáticamente en Alegra
- `allow_create_products`: Crear productos automáticamente en Alegra
- `dni_field`: Meta key personalizada para campo DNI (default: `_billing_dni`)

## Testing

### Requisitos

- PHPUnit 9.x
- WordPress test framework (`wp-phpunit`)
- MySQL/MariaDB
- WooCommerce instalado como plugin hermano

### Comandos

```bash
make test                 # Todos los tests
make test-calculate-dv    # Tests de cálculo DV
make test-invoice         # Tests de generación de facturas
make test-client          # Tests de gestión de clientes
```

### Suites de tests

- **Test_Integration_Alegra_WC**: Validación del cálculo del dígito de verificación (DV) para NITs colombianos
- **Test_Invoice_Generation**: Generación de facturas (estados, duplicados, validaciones)
- **Test_Client_Management**: Construcción de datos de contacto, extracción de DV, manejo de tipos de documento

### Ejecución local

```bash
# Configurar variable de entorno
export WP_TEST__DIR=/ruta/al/directorio/wp-tests

# Ejecutar
vendor/bin/phpunit --testdox
```

### CI/CD

- **tests.yml**: Ejecuta tests en push/PR a `main` (PHP 8.1, MySQL 5.7)
- **release.yml**: Crea release en GitHub al pushear tags `v*`

## Linting

```bash
composer phpcs          # WordPress Coding Standards check
composer phpcbf         # Auto-fix
composer phpcs-check    # Solo directorio includes/
```

Configuración en `phpcs.xml`. Prefijos globales requeridos: `integration_alegra` / `Integration_Alegra`.

## Convenciones de código

- **Namespaces**: Solo la librería client usa namespace (`Saulmoralespa\Alegra`). Las clases del plugin son globales
- **Métodos estáticos**: `Integration_Alegra_WC` usa exclusivamente métodos estáticos
- **Singleton**: `Integration_Alegra_WC_Plugin` se instancia una sola vez via `integration_alegra_wc_smp()`
- **Logging**: Usar `integration_alegra_wc_smp()->log($message, $level)` — escribe en logs de WooCommerce con source `integration-alegra`
- **Settings**: Se almacenan en `wp_options` con key `woocommerce_wc_alegra_integration_settings`
- **Sanitización**: Inputs via `sanitize_text_field()`, nonces con `wp_verify_nonce()`
- **Compatibilidad HPOS**: Declarada via `FeaturesUtil::declare_compatibility('custom_order_tables')`

## Actualización automática

El plugin usa `plugin-update-checker` para actualizarse desde GitHub releases del repositorio `saulmoralespa/integration-alegra-woo` (rama `main`).

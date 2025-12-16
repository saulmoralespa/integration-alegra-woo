=== Integration Alegra Woocommerce  ===
Contributors: saulmorales
Donate link: https://saulmoralespa.com/donation
Tags: commerce, e-commerce, commerce, WordPress ecommerce, store, sales, sell, shop, invoice, configurable, alegra, account, system
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.0.14
WC requires at least: 9.6
WC tested up to: 10.3
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Alegra Sistema contable y de facturación

== Description ==
Integración de Alegra para WooCommerce: Cree y emita facturas en tan solo unos simples pasos. ¡Solo necesita hacer clic en un botón!

== Installation ==

1. Descargar el plugin
2. Ingrese a su administrador de WordPress
3. Ingrese al menu plugins / añadir nuevo
4. Subir el plugin en formato .zip
5. Instale y active el plugin
6. Navegue a Woocommerce / Ajustes / Integración / Integration Alegra Woocommerce
7. Establezca las [credenciales API](https://mi.alegra.com/integrations/api) de la cuenta de Alegra
8. Realice demás configuraciones y guarde cambios


== Frequently Asked Questions ==

= ¿En qué estado queda la factura en Alegra al cambiar el estado de pedido en Woocommerce? =
Estado de DIAN: Por emitir, Estado: Por cobrar

= ¿ Puedo emitir desde el admin de Woocommerce ? =
Sí, puede emitir desde una o masivamente hasta 10 facturas

= ¿Qué campos personalizados agrega el plugin en el checkout? =
El plugin agrega automáticamente dos campos obligatorios en el proceso de pago:

1. **Tipo de documento**: Campo de selección con las siguientes opciones:
   - CC: Cédula de ciudadanía
   - NIT: Número de identificación tributaria
   - CE: Cédula de extranjería
   - DIE: Documento de identificación extranjero
   - TE: Tarjeta de extranjería
   - PP: Pasaporte
   - TI: Tarjeta de identidad
   - RC: Registro civil
   - FOREIGN_NIT: NIT de otro país

2. **Número de documento**: Campo numérico para ingresar el número de identificación (mínimo 5 dígitos)

Estos campos son obligatorios y se utilizan para crear el contacto en Alegra y generar la factura correctamente.

= ¿Puedo personalizar los campos de documento? =
Sí, los campos pueden ser personalizados utilizando plugins compatibles con WooCommerce como:

- **Custom WooCommerce Checkout Fields Editor**: Permite modificar etiquetas, placeholders, hacer campos opcionales, agregar validaciones personalizadas, etc.
- **Checkout Field Editor for WooCommerce**: Para reordenar, editar o agregar campos adicionales
- **WooCommerce Checkout Manager**: Para gestión avanzada de campos de checkout

**Nota importante**: Los campos `billing_type_document` y `billing_dni` son fundamentales para la integración con Alegra. Si los modifica o hace opcionales, asegúrese de que el cliente los complete para evitar errores en la generación de facturas.

= ¿Los campos son compatibles con WooCommerce Blocks? =
Sí, el plugin es compatible con el nuevo Checkout Block de WooCommerce. Los campos se registran usando la API moderna de WooCommerce y funcionan tanto en el checkout clásico como en el checkout por bloques.

= ¿ Qué validaciones tienen los campos de documento ? =
El plugin incluye validaciones automáticas:

- **Campo NIT**: Debe tener exactamente 9 dígitos (sin el dígito de verificación DV)
- **Número de documento**: Mínimo 5 dígitos, máximo 12 dígitos
- **Cálculo automático del DV**: Si selecciona NIT, el sistema calcula automáticamente el dígito de verificación

= ¿ Dónde se guardan los datos de los campos personalizados ? =
Los datos se guardan como metadatos de la orden en WordPress:
- `_billing_type_document` o `_shipping_type_document`: Tipo de documento seleccionado
- `_billing_dni` o `_shipping_dni`: Número de documento ingresado

Estos datos son editables desde el panel de administración de WooCommerce en cada pedido individual.

= ¿Qué hace la opción "Crear clientes automáticamente"? =
Esta opción controla si el plugin puede crear automáticamente contactos nuevos en Alegra cuando se genera una factura.

**Ubicación:** WooCommerce > Ajustes > Integración > Integration Alegra Woocommerce > Otras configuraciones

**Comportamiento según la configuración:**

**Habilitada (predeterminado - recomendado):**
- Si el cliente no existe en Alegra, el plugin lo crea automáticamente usando los datos de la orden
- La factura se genera sin problemas
- Ideal para tiendas con muchos clientes nuevos

**Deshabilitada:**
- Solo se generan facturas para clientes que YA existen en Alegra
- Si el cliente no existe, la factura NO se crea y se muestra un error
- El pedido incluirá una nota indicando que el cliente no existe
- Útil para empresas B2B que mantienen control estricto de su lista de clientes

**Datos que se sincronizan al crear un cliente:**
- Nombre completo (nombre y apellido)
- Tipo y número de documento (con DV calculado para NITs)
- Dirección completa (calle, ciudad, departamento, país)
- Teléfono y correo electrónico
- Tipo de persona (natural o jurídica según el tipo de documento)
- Régimen tributario (común para NIT, simplificado para otros)

**Recomendación:** Mantenga esta opción habilitada a menos que tenga un proceso manual de aprobación de clientes en Alegra.

= ¿Qué sucede si deshabilito la creación automática de clientes y el cliente no existe? =
Si la opción "Crear clientes automáticamente" está deshabilitada y el cliente no existe en Alegra:

1. La factura NO se generará
2. Se agregará una nota al pedido con el mensaje: "Cliente no existe y la creación de clientes está deshabilitada"
3. El pedido permanecerá sin factura de Alegra asociada
4. Deberá crear manualmente el cliente en Alegra con el mismo número de documento
5. Luego podrá generar la factura manualmente desde el panel de pedidos de WooCommerce

 = ¿Qué hace la opción "Crear productos automáticamente"? =
Esta opción controla si el plugin puede crear automáticamente productos nuevos en Alegra cuando se genera una factura.

**Ubicación:** WooCommerce > Ajustes > Integración > Integration Alegra Woocommerce > Otras configuraciones

**Comportamiento según la configuración:**

**Habilitada (predeterminado - recomendado):**
- Si un producto con SKU específico no existe en Alegra, el plugin lo crea automáticamente
- Los datos del producto se sincronizan desde WooCommerce a Alegra
- La factura se genera sin problemas
- Ideal para tiendas con catálogos dinámicos y productos nuevos frecuentes

**Deshabilitada:**
- Solo se generan facturas con productos que YA existen en Alegra
- Si un producto no existe, la factura NO se crea y se muestra un error
- El pedido incluirá una nota indicando qué producto falta: "El producto con SKU XXX no existe en Alegra"
- Útil para empresas que requieren control estricto del catálogo en Alegra

**Datos que se sincronizan al crear un producto:**
- Nombre del producto
- SKU (referencia única)
- Precio (precio de venta o precio regular)
- Tipo (producto físico o servicio)
- Descripción (primeros 50 caracteres sin HTML)
- Unidad de inventario (servicio para productos virtuales/descargables, centímetro para físicos)

**Nota:** El producto en WooCommerce debe tener un SKU configurado. Los productos sin SKU se omiten automáticamente.

**Recomendación:** Mantenga esta opción habilitada para sincronización automática. Desactívela solo si gestiona el catálogo manualmente en Alegra.

= ¿Qué sucede si deshabilito la creación automática de productos y un producto no existe? =
Si la opción "Crear productos automáticamente" está deshabilitada y un producto del pedido no existe en Alegra:

1. La factura NO se generará
2. Se agregará una nota al pedido indicando el SKU del producto que falta
3. El pedido permanecerá sin factura de Alegra asociada
4. Deberá crear manualmente el producto en Alegra con el mismo SKU
5. Asegúrese de que el SKU en Alegra coincida exactamente con el de WooCommerce
6. Luego podrá generar la factura manualmente desde el panel de pedidos

**Importante:** Si el pedido tiene múltiples productos y alguno no existe en Alegra, toda la factura se cancelará.

= ¿Puedo usar sincronización masiva de productos? =
Sí, el plugin incluye una opción de sincronización masiva de productos desde WooCommerce a Alegra.

**Cómo usar:**
1. Vaya a Productos en el panel de WordPress
2. Seleccione los productos que desea sincronizar
3. En "Acciones en lote" seleccione "Sincronizar con Alegra"
4. Haga clic en "Aplicar"

**Requisitos:**
- Los productos deben tener SKU configurado
- La opción "Crear productos automáticamente" puede estar habilitada o deshabilitada
- Si el producto ya existe en Alegra, se actualizará con los datos de WooCommerce

**Limitaciones:**
- Solo sincroniza productos que no hayan sido sincronizados previamente (sin meta `sync_alegra`)
- Los productos virtuales o descargables se crean como "servicio" en Alegra


== Screenshots ==

1. Configuración general screenshot-1.png
2.  screenshot-2.png
3.  screenshot-3.png
4.  screenshot-4.png
5.  screenshot-5.png

== Changelog ==

= 0.0.14 =
* Add invoice settings for order and invoice status configuration
* Refactor invoice column handling in WooCommerce admin orders
* Remove unused product description from invoice item data
* Enhance token validation with improved error handling and sanitization
= 0.0.13 =
* Added option to control automatic client creation in Alegra
* Added option to control automatic product creation in Alegra
* Added validation to prevent invoice creation when client doesn't exist and auto-creation is disabled
* Added validation to prevent invoice creation when product doesn't exist and auto-creation is disabled
* Improved error handling with order notes for client and product creation failures
* Refactored client lookup and creation logic for better maintainability
* Refactored product lookup and creation logic for better maintainability
* Refactored calculateDv to calculate_dv following WordPress coding standards
* Added protection against division by zero in discount calculations
* Improved code documentation with comprehensive PHPDoc blocks
= 0.0.12 =
* Fixed save type document
= 0.0.11 =
* Updated fields document, compatibility with checkout block
= 0.0.10 =
* Added log create invoice
= 0.0.9 =
* Fixed price item
* Added tax to shipping item
= 0.0.8 =
* Fixed get description product
* Updated compatibility with wordPress
= 0.0.7 =
* Fixed emit invoices
= 0.0.6 =
* Added discount in product
= 0.0.5 =
* Added option select tax
* Added item shipping cost
* Fixed tax in product
= 0.0.4 =
* Fixed generate invoice
= 0.0.3 =
* Added option select seller
* Added option select invoice status
* Updated valid NIT
* Updated create contact
= 0.0.2 =
* Fixed discount product
* Updated show fields in order view
= 0.0.1 =
* Initial beta release

== Credits ==
*  [Website](https://saulmoralespa.com) [Linkedin](https://www.linkedin.com/in/saulmoralespa/)
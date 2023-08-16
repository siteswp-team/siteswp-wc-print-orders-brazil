

# SitesWP Print Orders Brazil for WooCommerce

Plugin para impressão de etiquetas de envio e declaração dos correios a partir de pedidos do WooCommerce.

Enviem feedback, report de bugs e sugestões via [issues do github](https://github.com/siteswp-team/siteswp-woocommerce-print-orders-brazil/issues) 

Ajuda: preciso de ajuda para adicionar os outros modelos de etiquetas da pimaco. Além das medidas é preciso fazer testes com as folhas impressas, para certificar que o encaixe está correto. Então quem trabalhar com os outros modelos por favor envie as configurações de layout usadas.

## ToDo
- [ ] remover options do banco de dados ao desinstalar o plugin
- [ ] mais modelos para etiquetas (2x3)
- [ ] adicionar modelos de etiquetas adesivas
- [ ] imprimir mais declarações por página
- [ ] imprimir página apenas com remetente
- [ ] salvar opção de modelos escolhidos, ao utilizar novamente usará as opções da sessão anterior
- [ ] verificar nível de usuário
- [x] admin page para configurar opções(logo, cpf(?))
- [ ] adaptar interface para celulares
- [ ] múltiplas páginas, em caso de muitos produtos na declaração
- [ ] opção de "entrega no vizinho"

## Hooks

### Editar configurações

Utilizar o hook `swp_print_orders_config`:
```php
add_filter( 'swp_print_orders_config', 'custom_print_orders_config' );
function custom_print_orders_config( $config ){
    // adicionar arquivo CSS
    $config['css'] = array(
        'file' => ABS_PATH .  '/css/print-orders.css',
    );
    return $config;
}
```

Defaults de `$config`:
```php
protected $config = array(
    'debug'              => false,
    'paper'              => 'A4',  // tipo de papel
    'per_page'           => 10,
    'print_action'       => '',
    'layout' => array(
        'group' => 'percentage',
        'item' => '2x2',
    ),
    'images' => array(
        'logo' => false,
    ),
    'admin' => array(
        'title'               => 'Imprimir Etiquetas de endereços dos pedidos',
        'individual_buttons'  => true,       // botões de impressão individuais para cada pedido
        'layout_select'       => true,       // habilitar dropdown para seleção de layout, como modelos de etiquetas pimaco
        'print_invoice'       => true,       // imprimir página de declaração de contepúdo dos correios
        'invoice_group_items' => true,       // agrupar items na declaração
        'invoice_group_name'  => '',         // nome para agrupamento na declaração
    ),
    'css' => array(
        'base'    => '',    // CSS inline geral
        'preview' => '',    // CSS inline apenas para visualização
        'print'   => '',    // CSS inline apenas para impressão
        'file'    => '',    // arquivo adicional de css
    ),
    'barcode_config' => array(
        'width_factor' => 1,
        'height'       => 50,
    ),
);
```
### Adicionar layouts

É preciso que um layout esteja registrado em `$layouts` para que possa ser utilizado. Utilizar o hook `swp_print_orders_layouts`.

Exemplo adicionando um novo grupo de layouts chamado "Custom", com dois layouts, "foo" e "bar":
```php
add_filter( 'swp_print_orders_layouts', 'custom_print_orders_layouts' );
function custom_print_orders_layouts( $layouts ){
    $layouts['custom'] = array(
        'name' => 'Custom',
        'items' => array(
            'foo' => array(
                'name'         => 'Foo',
                'paper'        => 'A4',
                'per_page'     => 6,
                'width'        => '90mm',
                'height'       => '80mm',
                'page_margins' => '15mm 10mm 0 15mm',
                'item_margin'  => '0 0 0 0',
            ),
            'bar' => array(
                'name'         => 'Bar',
                'paper'        => 'Letter',
                'per_page'     => 8,
                'width'        => '90mm',
                'height'       => '30mm',
                'page_margins' => '0 0 0 0',
                'item_margin'  => '0 0 0 0',
            ),
        ),
    );
    
    return $layouts;
}
```

----------

## Dados adicionais

Este plugin está usando um campo extra para pegar informações da loja. Em `wp-admin/admin.php?page=wc-settings` temos os dados de endereço da loja. É possível adicionar um campo de texto para o CPF/CNPJ da loja, para poder preencher a declaração dos correios.

O exemplo abaixo adiciona o campo `woocommerce_store_cpf_cnpj` após o campo de CEP:
```php

add_filter( 'woocommerce_general_settings', 'swp_woocommerce_general_settings' );
function swp_woocommerce_general_settings( $settings ){
    
    $return = array();
    foreach( $settings as $i => $s ){
        $return[] = $s;
        if( $s['id'] == 'woocommerce_store_postcode' ){
            $return[] = array(
                'title'    => 'CPF/CNPJ',
                'desc'     => 'CPF da pessoa física responsável ou CNPJ da loja.',
                'id'       => 'woocommerce_store_cpf_cnpj',
                'default'  => '',
                'type'     => 'text',
                'desc_tip' => true,
            );
        }
    }
    
    return $return;
}
```

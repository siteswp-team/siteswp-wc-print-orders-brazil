<?php
/**
 * Plugin Name:       SitesWP Etiquetas de Pedidos e Declaração de Conteúdo Correios Brasil
 * Plugin URI:        https://github.com/siteswp-team/siteswp-woocommerce-print-orders-brazil
 * Description:       Imprimir etiquetas de pedidos e declaração de conteúdo para os Correios do BRasil, para pedidos gerados no WooCommerce.
 * Author:            SitesWP
 * Author URI:        https://siteswp.com.br/
 * Version:           1.0.0
 * Requires at least: 5.2
 * Tested up to:      6.0
 * Requires PHP:      7.2
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Tags:              woocommerce, shipping, correios, brasil
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Hooks
 * 
 */
add_action( 'admin_footer-edit.php', array('SWP_Print_Orders', 'footer') );             // Adicionar botões de impressão nas páginas de pedido
add_filter( 'woocommerce_general_settings', 'swp_print_orders_add_shop_cnpj_cpf' );     // Adicionar CPF/CNPJ para opções da loja

/**
 * Adicionar campo de CNPJ nas configurações do WooCommerce
 * 
 */
function swp_print_orders_add_shop_cnpj_cpf( $settings ){
    
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
};

/**
 * Inicializar classe
 * 
 */
$swp_print_orders = new SWP_Print_Orders();

/**
 * Classe principal
 * 
 */
class SWP_Print_Orders {
    
    /**
     * Exbir debug
     * 
     */
    protected $debug = false;
    
    /**
     * URL do plugin
     * 
     */
    private $plugin_url = '';
    
    /**
     * Cópia do $wp_locale
     * 
     */
    protected $locale = array();
    
    /**
     * Qual elemento será impresso:
     * - 'order_slip'   etiqueta correios
     * - 'invoice'      declaração correios
     * 
     */
    protected $print_action = 'order_slip';
    
    /**
     * Informações da loja/remetente
     * 
     */
    protected $store_info = array();
    
    /**
     * IDs dos pedidos a serem impressos
     * 
     */
    protected $order_ids = array();
    
    /**
     * Quantidade de slots para pular, em caso de não precisar imprimir desde a primeira etiqueta
     * 
     */
    protected $offset = 0;
    
    /**
     * Quantidade de etiquetas por folha, vai depender do layout da página
     * 
     */
    protected $per_page = 0;
    
    /**
     * Tamanhos de papael disponíveis
     * 
     */
    protected $papers = array(
        'A4' => array(
            'name'         => 'A4',
            'width'        => '210',
            'height'       => '297',
            'unit'         => 'mm',
        ),
        'Letter' => array(
            'name'         => 'Letter',
            'width'        => '216',
            'height'       => '279',
            'unit'         => 'mm',
        ),
    );
    
    /**
     * Papel utilizado atualmente
     * 
     */
    protected $paper = false;
    
    /**
     * Layouts de etiquetas individuais, Largura X Altura
     * Lista de modelos pré-determinados que poderão ser escolhidos.
     * Começar a lista com divisões simples e modelos de etiquetas adesivas pimaco
     * 
     */
    protected $layouts = array(
        'percentage' => array(
            'name' => 'Simples',
            'items' => array(
                '2x2' => array(
                    'name'         => '2x2',
                    'paper'        => 'A4',
                    'per_page'     => 4,
                    'page_margins' => '10mm 10mm 10mm 10mm',
                    'width'        => '50%',
                    'height'       => '50%',
                    'item_margin'  => '0 0 0 0',
                ),
            ),
        ),
    );
    
    /**
     * Layout da etiqueta individual
     * 
     */
    protected $layout = array();
    
    /**
     * Layouts default
     * 
     */
    protected $layout_default = array(
        'name'         => 'custom',
        'paper'        => 'A4',
        'per_page'     => 10,
        'page_margins' => '0 0 0 0',
        'width'        => '50%',
        'height'       => '100px',
        'item_margin'  => '0 0 0 0',
    );
    
    /**
     * Array de imagens em base64, para serem usadas nas etiquetas individuais
     * 
     */
    protected $images = array(
        'logo' => false,
    );
    
    protected $barcode_config = array(
        'width_factor' => 0,
        'height'       => 0,
    );
    
    /**
     * Etiqueta individual atual
     * 
     */
    protected $label = array();
    
    /**
     * Configuração da página do admin
     * 
     */
    protected $admin_title = '';
    
    protected $individual_buttons = true;
    
    protected $layout_select = true;
    
    protected $print_invoice = true;
    
    protected $invoice_group_items = false;
    
    protected $invoice_group_name = '';

    protected $invoice_group_empty_rows = 0;
    
    /**
     * Configuração padrão
     * 
     */
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
            'title'                    => 'Impressão de etiquetas e declaração',
            'individual_buttons'       => true,       // botões de impressão individuais para cada pedido
            'layout_select'            => true,       // habilitar dropdown para seleção de layout, como modelos de etiquetas pimaco
            'print_invoice'            => true,       // imprimir página de declaração de contepúdo dos correios
            'invoice_group_items'      => false,      // agrupar items na declaração
            'invoice_group_name'       => '',         // nome para agrupamento na declaração
            'invoice_group_empty_rows' => 5,          // quantidade de linhas em branco após a listagem resumida
        ),
        'css' => array(
            'base'    => '',
            'preview' => '',
            'print'   => '',
            'file'    => '',
        ),
        'barcode_config' => array(
            'width_factor' => 1.8,
            'height'       => 45,
        ),
    );
    
    /**
     * Valores permitidos de formulários
     * 
     */
    protected $form_vars = array(
        'print_action' => array(
            'type' => 'in_array',
            'args' => array(
                'order_slip',
                'invoice',
            ),
        ),
        'offset' => array(
            'type' => 'natural_number',
        ),
    );
    
    protected $orders = array();
    
    public function __construct(){
        add_action( 'admin_menu', [$this, 'admin_menu'], 60 );
        add_action( 'admin_enqueue_scripts', [$this, 'setup_init'], 1 );
        add_action( 'admin_enqueue_scripts', [$this, 'enqueues'], 10 );
    }

    public function admin_menu( $hook ){
        add_submenu_page(
            'woocommerce'
            , 'Imprimir Etiquetas'
            , 'Imprimir Etiquetas'
            , 'manage_options'
            , 'correios_print_orders'
            , [$this, 'render_page']
        );
    }

    public function setup_init( $hook ){
        if( $hook == 'woocommerce_page_correios_print_orders' ){
            $this->setup();
        }
    }

    public function enqueues( $hook ){
        if( $hook == 'woocommerce_page_correios_print_orders' ){
            echo $this->css_base();
            echo $this->css_preview();
            echo $this->css_print();
            if( !empty($this->config['css']['file']) ){
                echo "<link rel='stylesheet' href='{$this->config['css']['file']}' />";
            }
        }
    }

    function setup(){
        global $wp_locale;
        $this->locale = $wp_locale;
        
        $this->plugin_url = plugin_dir_url( __FILE__ );
        
        // definir os pedidos
        $this->set_orders();
        
        $custom_config = apply_filters( 'swp_print_orders_config', $this->config );
        $this->config = array_replace_recursive( $this->config, $custom_config );
        
        // definir status do debug
        $this->debug = $this->config['debug'];
        
        // definir print action
        $this->print_action = $this->config['print_action'];
        $this->set_form_var('print_action', $this->print_action);
        
        // adicionar layouts extras
        $this->set_layouts();
        
        // definir layout
        if( isset( $this->layouts[ $this->config['layout']['group'] ]['items'][ $this->config['layout']['item'] ] ) ){
            $this->layout = $this->layouts[ $this->config['layout']['group'] ]['items'][ $this->config['layout']['item'] ];
        }
        
        // definir papel
        $this->paper = $this->papers[ $this->layout['paper'] ];
        
        // ajustar altura das etiquetas calculando a medida do paper com as margens
        if( $this->config['layout']['group'] == 'percentage' ){
            $margim = str_replace( 'mm', '', explode( ' ', $this->layout['page_margins'] ) );
            $divider = str_replace('%', '', $this->layout['height']);
            $this->layout['height'] = ( ($divider / 100) * ($this->paper['height'] - $margim[0] - $margim[2]) ) . 'mm';
        }
        
        // quantidade de etiquetas por página
        $this->per_page = $this->layout['per_page'];
        
        // definir etiqueta individual
        $this->label = array(
            'width'        => $this->layout['width'],
            'height'       => $this->layout['height'],
            'item_margin'  => $this->layout['item_margin'],
        );
        
        // definir offset
        //$this->offset = isset($_GET['offset']) ? $_GET['offset'] : 0;
        $this->set_form_var('offset', 0);
        if( $this->offset > ($this->per_page - 1) ){
            $this->offset = ($this->per_page - 1);
        }
        
        // definir imagens personalizadas
        if( is_array($this->config['images']) ){
            $this->images = wp_parse_args( $this->config['images'], $this->images );
        }
        
        // definir configurações da página do admin
        $this->admin_title              = $this->config['admin']['title'];
        $this->individual_buttons       = $this->config['admin']['individual_buttons'];
        $this->layout_select            = $this->config['admin']['layout_select'];
        $this->print_invoice            = $this->config['admin']['print_invoice'];
        $this->invoice_group_items      = $this->config['admin']['invoice_group_items'];
        $this->invoice_group_name       = $this->config['admin']['invoice_group_name'];
        $this->invoice_group_empty_rows = $this->config['admin']['invoice_group_empty_rows'];
        
        // definir configurações do código de barras
        $this->barcode_config = $this->config['barcode_config'];
        
        // definir informações da loja/remtente
        $this->set_sender();
    }
    
    public function render_page(){
        ?>
        <div class="wrap" id="swp-print-orders">
            <h2 class="no-print"><?php echo $this->admin_title; ?></h2>
            
            <form action="" method="get" class="print-config-form no-print">
                <input type="hidden" name="page" value="correios_print_orders" />
                <input type="hidden" name="oid" value="<?php echo implode(',', $this->order_ids); ?>" />
                
                <?php
                switch( $this->print_action ){
                    case 'invoice':
                        echo '<h3>Imprimindo declaração de conteúdo</h3>';
                        break;

                    case 'order_slip':
                    default:
                        echo '<h3>Imprimindo etiquetas de postagem dos correios</h3>';
                        break;
                }
                ?>
                
                <div class="fieldsets">
                    <?php $this->print_action_bar(); ?>
                    
                    <?php if( empty($this->print_action) || $this->print_action == 'order_slip' ){ ?>
                    <fieldset>
                        <legend>Offset:</legend>
                        <p>Pular <input type="number" name="offset" value="<?php echo $this->offset; ?>" size="2" min="0" max="<?php echo (int)$this->per_page - 1; ?>" /> itens no começo da impressão. <button type="submit" name="print_action" value="order_slip" class="button-primary">atualizar</button></p>
                    </fieldset>
                    <?php } ?>
                </div>
            </form>
            
            <div class="preview-label no-print">
                <p><a href="javascript: window.print();" class="button-primary btn-print">IMPRIMIR</a></p>
                <h2 id="preview-title">Visualização:</h2>
                <p>As linhas pontilhadas vermelhas não serão impressas.</p>
            </div>
            
            <?php
            switch( $this->print_action ){
                    
                case 'invoice':
                    $this->print_invoices();
                    break;
                
                case 'order_slip':
                default:
                    $this->print_pages();
                    break;
            }
            ?>
            
            
            <?php
            if( $this->debug == true ){
                echo '<div class="no-print"><pre>';
                print_r( $this->config );
                echo '<hr>';
                print_r( $this );
                echo '</pre></div>';
            }
            ?>
        </div>
        <?php
        
        die();
    }
    
    /**
     * Definir variável de formulário
     * Utilizar valor enviado via $_GET ou utilizar valor padrão
     * Validar os dados conforme o tipo.
     * 
     */
    protected function set_form_var( $name, $default = false ){
        $value = isset($_GET[$name]) ? $_GET[$name] : $default;
        $v = false;
        
        if( isset($this->form_vars[$name]) ){
            switch( $this->form_vars[$name]['type'] ){
                case 'in_array':
                    if( in_array( $value, $this->form_vars[$name]['args'] ) ){
                        $this->$name = $value;
                    }
                    break;
                
                case 'natural_number':
                    $int = filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0)));
                    $this->$name = ($int == false) ? 0 : $int;
                    break;
                    
                default:
                    break;
            }
        }
        
        return $v;
    }
    
    /**
     * Barra de controle para escolher o tipo de impressão:
     * - 'order_slip'   etiqueta dos correios
     * - 'invoice'      invoice(declaração correios)
     * 
     */
    protected function print_action_bar(){
        ?>
        <fieldset>
            <legend>Escolha o tipo:</legend>
            <button type="submit" name="print_action" value="order_slip" class="button-primary" id="print-btn-order-slip">Etiquetas</button>
            <button type="submit" name="print_action" value="invoice" class="button-primary" id="print-btn-invoice">Declaração</button>
        </fieldset>
        <?php
    }
    
    protected function set_orders(){
        
        if( isset($_GET['oid']) ){
            $this->order_ids = explode(',', $_GET['oid']);
        }
        
        foreach( $this->order_ids as $id ){
            $order = new WC_Order( $id );
            
            // buscar informações de endereço
            $address = $this->get_address( $order );
            // guardar informações de endereço para serem usadas no invoice
            $order->address_print = $address;
            //pre($address);
            
            // guardar o pedido em orders
            $this->orders[ $id ] = $order;
        }

        $this->orders = apply_filters( 'swp_print_orders', $this->orders );
    }
    
    protected function print_pages(){
        echo "<div class='paper paper-{$this->paper['name']}'>";
            $total = 0;
            $cel = 1;
            if( $this->offset > 0 ){
                for( $i = 1; $i <= $this->offset; $i++ ){
                    echo '<div class="order empty"><span>vazio</span></div>';
                    if( $cel == 2 ){
                        //echo '<hr />';
                        $cel = 1;
                    }
                    else{
                        $cel++;
                    }
                    $total++;
                }
            }
            
            foreach( $this->orders as $order ){
                echo "<div class='order layout-{$this->layout['name']}'>";
                $this->print_order( $order );
                echo '</div>';
                if( $cel == 2 ){
                    $cel = 1;
                }
                else{
                    $cel++;
                }
                $total++;
                
                if( $total % $this->per_page == 0 && $total != (count($this->order_ids) + $this->offset) ){
                    echo "</div><div class='paper paper-{$this->paper['name']}'>";
                }
            }
            
            $empty = ($this->per_page - ($this->offset + count($this->order_ids)));
            if( $empty > 0 ){
                for( $n = 1; $n <= $empty; $n++ ){
                    echo "<div class='order empty paper-{$this->paper['name']} layout-{$this->layout['name']}''><span>vazio</span></div>";
                    if( $cel == 2 ){
                        $cel = 1;
                    }
                    else{
                        $cel++;
                    }
                    $total++;
                }
            }
            //pal($total);
        echo '</div>';
    }
    
    protected function print_order( $order ){
        include_once( 'vendor/php-barcode-generator/src/BarcodeGenerator.php');
        include_once( 'vendor/php-barcode-generator/src/BarcodeGeneratorPNG.php');
        
        $address = $order->address_print;
        
        $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
        $barcode = base64_encode($generator->getBarcode($address['cep'], $generator::TYPE_CODE_128, $this->barcode_config['width_factor'], $this->barcode_config['height']));
        
        // Etiqueta individual
        $label  = new SWP_Print_Order_Label_2x2( $order, $address, $barcode );
        $output = $label->get_label();
        echo apply_filters( 'swp_print_orders_customer_label', $output, $order, $address, $barcode);
    }
    
    protected function get_address( $order ){
        
        $order_data = $order->get_data();
        $order_meta_data = $order->get_meta_data();
        //pre($order_data, 'order_data', false);
        //pre($order_meta_data, 'order_meta_data', false);
        //pre($order_data['shipping'], '$order_data[shipping]', false);
        
        if( empty( $order_data['shipping']) ){
            $number       = $this->get_address_meta_data( $order_meta_data, '_billing_number' );
            $neighborhood = $this->get_address_meta_data( $order_meta_data, '_billing_neighborhood' );
            $address = array(
                'nome'           => "{$order_data['billing']['first_name']} {$order_data['billing']['last_name']}",
                'empresa'        => empty($order_data['billing']['company']) ? '' : " - {$order_data['billing']['company']}",
                'logradouro'     => "{$order_data['billing']['address_1']} {$number}",
                'complemento'    => empty($order_data['billing']['address_2']) ? '' : ", {$order_data['billing']['address_2']}",
                'bairro'         => empty($neighborhood) ? '' : "{$neighborhood}",
                'cidade'         => $order_data['billing']['city'],
                'uf'             => empty($order_data['billing']['state']) ? '' : " - {$order_data['billing']['state']}",
                'cep'            => $order_data['billing']['postcode'],
            );
        }
        else{
            $number       = $this->get_address_meta_data( $order_meta_data, '_shipping_number' );
            $neighborhood = $this->get_address_meta_data( $order_meta_data, '_shipping_neighborhood' );
            $address = array(
                'nome'           => "{$order_data['shipping']['first_name']} {$order_data['shipping']['last_name']}",
                'empresa'        => empty($order_data['shipping']['company']) ? '' : " - {$order_data['shipping']['company']}",
                'logradouro'     => "{$order_data['shipping']['address_1']} {$number}",
                'complemento'    => empty($order_data['shipping']['address_2']) ? '' : ", {$order_data['shipping']['address_2']}",
                'bairro'         => empty($neighborhood) ? '' : "{$neighborhood}",
                'cidade'         => $order_data['shipping']['city'],
                'uf'             => empty($order_data['shipping']['state']) ? '' : $order_data['shipping']['state'],
                'cep'            => $order_data['shipping']['postcode'],
            );
        }
        $address = $this->validate_address( $address );
        $address = apply_filters( 'swp_print_orders_customer_address', $address, $order );
        return $address;
    }
    
    /**
     * Validar dados do endereço para certificar de que não existem campos faltantes.
     * Exibe um texto de alerta destacado para a visualização no navegador. A versão impressa não exibe o alerta.
     * 
     */
    protected function validate_address( $address ){
        $optional = array(
            'empresa',
            'complemento',
        );
        foreach( $address as $key => $value ){
            if( !in_array($key, $optional) ){
                $cleanned = trim($value);
                if( empty($cleanned) or ($key == 'logradouro' and strlen($value) < 4) ){
                    $address[ $key ] = "<span class='empty-data' style='font-size:12pt;color:red;text-transform:uppercase;'>[{$key} VAZIO]</span>{$value}";
                }
            }
        }
        return $address;
    }
    
    protected function get_address_meta_data( $meta_data, $key ){
        foreach( $meta_data as $md ){
            $d = $md->get_data();
            if( $d['key'] == $key ){
                return $d['value'];
            }
        }
    }
    
    /**
     * Montar dados do remetente
     * 
     */
    protected function set_sender(){
        
        $store_info = array(
            'blogname'                    => '',
            'woocommerce_store_address'   => '',
            'woocommerce_store_address_2' => '',
            'woocommerce_store_postcode'  => '',
            'woocommerce_store_city'      => '',
            'woocommerce_store_cpf_cnpj'  => '',
        );
        foreach( $store_info as $k => $v ){
            $store_info[ $k ] = get_option( $k );
        }
        
        $_country = wc_get_base_location();
        $store_info['state'] = $_country['state'];
        
        $this->store_info = $store_info;
    }
    
    /**
     * Imprimir declaração dos correios
     * 
     */
    protected function print_invoices(){
        echo apply_filters( 'swp_print_orders_single_invoice_output_start', '' );
        $i = 0;
        foreach( $this->orders as $id => $order ){
            $invoice = $this->set_invoice( $order );
            echo apply_filters( 'swp_print_orders_single_invoice_output', "<div class='paper invoice'><div class='invoice-inner'>{$invoice}</div></div>", $invoice, $id, $i);
            $i++;
        }
        echo apply_filters( 'swp_print_orders_single_invoice_output_end', '' );
    }
    
    /**
     * Definir conteúdo da declaração
     * 
     */
    protected function set_invoice( $order ){
        
        $invoice = " ==== {$order->get_id()} ====";
        
        $invoice_info = array(
            'signature' => array(
                'day'      => date('d'),
                'month'    => $this->locale->month_genitive[ date('m') ],
                'year'     => date('Y'),
            ),
        );
        //pre($order, 'order', false);
        //pre($order->get_total(), 'get_total', true);
        //pre($invoice_info);
        //pre($this->store_info);
        //pre($order->address_print);
        //pre($this->locale);
        
        $group_title    = $this->invoice_group_name;
        $quantity_total = 0;
        $weight_total   = 0;
        $subtotal       = 0;
        $items          = $order->get_items();
        $order_items    = array();
        foreach( $items as $id => $product ){
            $p              = $product->get_product();
            $product_data   = $product->get_data();
            $weight         = (float) $p->get_weight() * $product_data['quantity'];
            $product_data   = $product->get_data();
            $quantity_total += $product_data['quantity'];
            $weight_total   += $weight;
            $subtotal       += (double) $product->get_subtotal();
            $order_items[] = array(
                'name'     => $product_data['name'],
                'quantity' => $product_data['quantity'],
                'price'    => $product->get_subtotal(),
                'weight'   => $weight,
            );
        }
        // arredondar apenas para gramas
        if( get_option('woocommerce_weight_unit') == 'g' ){
            $weight_total = round($weight_total);
        }
        $order_items = apply_filters( 'swp_print_orders_invoice_order_items', $order_items );
        //pre($subtotal, 'subtotal');
        //pre($order_items, 'order_items');
        
        ob_start();
        ?>
        <div class="invoice-page">
            <h1 class="invoice-logo">
                <img src="<?php echo $this->plugin_url; ?>/assets/img/logo-correios.svg" alt="" class="correios-logo" />
                Declaração de Conteúdo
            </h1>
            <!-- remetente -->
            <table class="invoice-sender" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="sender"><strong class="label">REMETENTE:</strong> <span class="value"><?php echo $this->store_info['blogname']; ?></span></td>
                    <td class="document"><strong class="label">CPF/CNPJ:</strong> <span class="value"><?php echo $this->store_info['woocommerce_store_cpf_cnpj']; ?></span></td>
                </tr>
                <tr>
                    <td colspan="2" class="address"><strong class="label">ENDEREÇO:</strong> <span class="value"><?php echo "{$this->store_info['woocommerce_store_address']}, {$this->store_info['woocommerce_store_address_2']}"; ?></span></td>
                </tr>
                <tr>
                    <td class="city-state"><strong class="label">CIDADE/UF:</strong> <span class="value"><?php echo "{$this->store_info['woocommerce_store_city']} / {$this->store_info['state']}"; ?></span></td>
                    <td class="zip-code"><strong class="label">CEP:</strong> <span class="value"><?php echo $this->store_info['woocommerce_store_postcode']; ?></span></td>
                </tr>
            </table>
            <!-- destinatário -->
            <table class="invoice-client" cellpadding="0" cellspacing="0">
                <tr>
                    <td class="receiver"><strong class="label">DESTINATÁRIO:</strong> <span class="value"><?php echo $order->address_print['nome']; ?></span></td>
                    <td class="document"><strong class="label">CPF/CNPJ:</strong> <span class="value"><?php echo $order->get_meta('_billing_cpf'); ?></span></td>
                </tr>
                <tr>
                    <td colspan="2" class="address"><strong class="label">ENDEREÇO:</strong> <span class="value"><?php echo "{$order->address_print['logradouro']}{$order->address_print['complemento']}, {$order->address_print['bairro']}"; ?></span></td>
                </tr>
                <tr>
                    <td class="city-state"><strong class="label">CIDADE/UF:</strong> <span class="value"><?php echo "{$order->address_print['cidade']} / {$order->address_print['uf']}"; ?></span></td>
                    <td class="zip-code"><strong class="label">CEP:</strong> <span class="value"><?php echo $order->address_print['cep']; ?></span></td>
                </tr>
            </table>
            <!-- lista de itens -->
            <table class="invoice-order-items" cellpadding="0" cellspacing="0">
                <tr>
                    <th colspan="4" class="invoice-title">IDENTIFICAÇÃO DOS BENS</th>
                </tr>
                <tr>
                    <th class="label">ITEM</th>
                    <th class="label">DISCRIMINAÇÃO DO CONTEÚDO</th>
                    <th class="label">QTD.</th>
                    <th class="label">VALOR</th>
                </tr>

                <?php if( $this->invoice_group_items == true ){ ?>
                    <tr>
                        <td class="item-value group-item"></td>
                        <td class="item-value group-title"><?php echo $group_title; ?></td>
                        <td class="item-value group-quantity"><?php echo $quantity_total; ?></td>
                        <td class="item-value group-weight"><?php echo wc_format_weight($weight_total); ?></td>
                    </tr>
                    <?php for($u = 0; $u < $this->invoice_group_empty_rows; $u++){ ?>
                    <tr>
                        <td class="item-value empty">&nbsp;</td>
                        <td class="item-value empty">&nbsp;</td>
                        <td class="item-value empty">&nbsp;</td>
                        <td class="item-value empty">&nbsp;</td>
                    </tr>
                    <?php } ?>
                <?php } else { ?>
                <?php foreach( $order_items as $i => $item ){ ?>
                    <tr class="order-items">
                        <td class="item-value group-item"><?php echo $i + 1; ?></td>
                        <td class="item-value item-name"><?php echo $item['name']; ?></td>
                        <td class="item-value item-quantity"><?php echo $item['quantity']; ?></td>
                        <td class="item-value item-price"><?php echo wc_price($item['price']); ?></td>
                    </tr>
                <?php } ?>
                <?php } ?>

                <tr class="total">
                    <td colspan="2" class="label-right">TOTAIS</td>
                    <td><?php echo $quantity_total; ?></td>
                    <td class="order-total"><?php echo wc_price($subtotal); ?></td>
                </tr>
                <tr class="total">
                    <td colspan="2" class="label-right">PESO TOTAL</td>
                    <td colspan="2"><?php echo wc_format_weight($weight_total); ?></td>
                </tr>
            </table>
            <!-- declaração -->
            <table class="invoice-disclaimer" cellpadding="0" cellspacing="0">
                <tr>
                    <th class="invoice-title">DECLARAÇÃO</th>
                </tr>
                <tr>
                    <td>
                        <div class="text">
                            Declaro que não me enquadro no conceito de contribuinte previsto no art. 4º da Lei Complementar nº 87/1996, uma vez que não realizo, com habitualidade ou em volume que caracterize intuito comercial, operações de circulação de mercadoria, ainda que se iniciem no exterior, ou estou dispensado da emissão da nota fiscal por força da legislação tributária vigente, responsabilizando-me, nos termos da lei e a quem de direito, por informações inverídicas.
                        </div>
                        <div class="text">
                            Declaro ainda que não estou postando conteúdo inflamável, explosivo, causador de combustão espontânea, tóxico, corrosivo, gás ou qualquer outro conteúdo que constitua perigo, conforme o art. 13 da Lei Postal nº 6.538/78 
                        </div>
                        
                        <div class="signature-date">
                            <div class="date">
                                <span class="underline"><?php echo $this->store_info['woocommerce_store_city']; ?></span>, 
                                <span class="underline"><?php echo $invoice_info['signature']['day']; ?></span> de 
                                <span class="underline"><?php echo $invoice_info['signature']['month']; ?></span> de 
                                <span class="underline"><?php echo $invoice_info['signature']['year']; ?></span>
                            </div>
                            <div class="signature">
                                Assinatura do Declarante/Remetente
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
            <!-- observações -->
            <table class="invoice-obs" cellpadding="0" cellspacing="0">
                <tr>
                    <td><strong>Atenção:</strong> O declarante/remetente é responsável exclusivamente pelas informações declaradas.</td>
                </tr>
                <tr>
                    <td>
                        <strong>OBSERVAÇÕES:</strong>
                        <ol>
                            <li>É Contribuinte de ICMS qualquer pessoa física ou jurídica, que realize, com habitualidade ou em volume 
                            que caracterize intuito comercial, operações de circulação de mercadoria ou prestações de serviços de 
                            transportes interestadual e intermunicipal e de comunicação, ainda que as operações e prestações se 
                            iniciem no exterior (Lei Complementar nº 87/96 Art. 4º).</li>
                            <li>Constitui crime contra a ordem tributária suprimir ou reduzir tributo, ou contribuição social e 
                            qualquer acessório: quando negar ou deixar de fornecer, quando obrigatório, nota fiscal ou documento 
                            equivalente, relativa a venda de mercadoria ou prestação de serviço, efetivamente realizada, ou fornecê-la 
                            em desacordo com a legislação. Sob pena de reclusão de 2 (dois) a 5 (anos), e multa (Lei 8.137/90 Art. 1º, V). 
                            </li>
                        </ol>
                    </td>
                </tr>
            </table>
        </div>
        <?php
        $invoice = ob_get_contents();
        ob_end_clean();
        
        return apply_filters( 'swp_print_orders_invoice', $invoice, $order, $this );
    }
    
    /**
     * Definir layouts disponíveis e adicionais custom
     * 
     */
    protected function set_layouts(){
        // layouts filtrado com customs
        $layouts = apply_filters( 'swp_print_orders_layouts', $this->layouts );
        
        if( is_array($layouts) and !empty($layouts) ){
            // resetar layouts
            $this->layouts = array();
            
            // readicionar layouts
            foreach( $layouts as $slug => $layout ){
                // apenas com nome de grupo e itens definidos
                if( isset($layout['name']) && isset($layout['items']) and !empty($layout['items']) ){
                    $this->add_layout_group( $slug, $layout['name'] );
                    foreach( $layout['items'] as $ls => $args ){
                        $args = wp_parse_args( $args, $this->layout_default );
                        $this->add_layout_item( $slug, $ls, $args );
                    }
                }
            } 
        }
    }
    
    /**
     * Adicionar grupo de layout
     * 
     */
    protected function add_layout_group( $slug, $name ){
        if( !isset( $this->layouts[$slug] ) ){
            $this->layouts[$slug] = array(
                'name' => $name,
                'items' => array(),
            );
        }
    }
    
    /**
     * Adicionar layout dentro de grupo
     * 
     */
    protected function add_layout_item( $group, $slug, $args ){
        if( isset( $this->layouts[$group] ) && !isset( $this->layouts[$group]['items'][$slug] ) ){
            $this->layouts[$group]['items'][$slug] = array(
                'name'         => $args['name'],
                'paper'        => $args['paper'],
                'page_margins' => $args['page_margins'],
                'per_page'     => $args['per_page'],
                'width'        => $args['width'],
                'height'       => $args['height'],
                'item_margin'  => $args['item_margin'],
            );
        }
    }
    
    public static function footer(){
        global $typenow;
        if( $typenow != 'shop_order' ){
            return;
        }
        
        $url = add_query_arg(array('page' => 'correios_print_orders'), admin_url('admin.php'));
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($){
                console.log('swp-woocommerce-print-orders');
                // add/update querystring
                // @link http://stackoverflow.com/a/6021027
                function updateQueryStringParameter(uri, key, value) {
                    var re = new RegExp("([?&])" + key + "=.*?(&|$)", "i");
                    var separator = uri.indexOf('?') !== -1 ? "&" : "?";
                    if (uri.match(re)) {
                        return uri.replace(re, '$1' + key + "=" + value + '$2');
                    }
                    else {
                        return uri + separator + key + "=" + value;
                    }
                }
                
                $('input:checkbox[name="post[]"], #cb-select-all-1').on('change', function(){
                    var ids_arr = [];
                    $('input:checkbox[name="post[]"]:checked').each(function() {
                        ids_arr.push(this.value);
                    });
                    var url = updateQueryStringParameter( $('#excs-print-orders-button').attr('href'), 'oid', ids_arr.join(',') );
                    $('#excs-print-orders-button').attr('href', url);
                });
                $('<a href="<?php echo $url; ?>" class="button" target="_blank" id="excs-print-orders-button">Imprimir Pedidos Selecionados</a>').insertAfter('#post-query-submit');
                
                // botão individual
                if( $('.column-order_number .excs-order-items').length ){
                    $('.column-order_number .excs-order-items').each(function( index ){
                        var id = $(this).closest('tr').attr('id').replace('post-', '');
                        $('<a href="<?php echo $url; ?>&oid=' + id + '" class="button print-barcode" target="_blank" title="imprimir etiqueta individual">Etiqueta </a>').insertAfter( $(this) );
                    });
                }
                else{
                    $('.order-preview').each(function( index ){
                        var id = $(this).closest('tr').attr('id').replace('post-', '');
                        $('<a href="<?php echo $url; ?>&oid=' + id + '" class="button print-barcode" target="_blank" title="imprimir etiqueta individual"></a>').insertAfter( $(this) );
                    });
                }
            });
        </script>
        <style type="text/css">
            /**
             * Botão de imprimir selecionados
             * 
             */
            #excs-print-orders-button {
                display: inline-block;
                margin: 1px 8px 0 0;
            }
            
            /**
             * Botão de imprimir pedido individual
             * 
             */
            .wp-core-ui .print-barcode {
                margin: 10px 10px 0 0;
                padding: 1px 7px 0;
            }
            .wp-core-ui .order-preview + .print-barcode {
                margin: 0 10px 0 0;
                float: left;
            }
            .wp-core-ui .print-barcode:after {
                font-family: WooCommerce;
                content: '\e006';
            }
            @media only screen and (max-width: 782px) {
                .wp-core-ui .print-barcode {
                    float: left;
                    margin: 0 0 0 10px;
                }
                .post-type-shop_order .wp-list-table .column-order_status mark {
                    float: left;
                }
            }
        </style>
        <?php
    }
    
    /**
     * CSS comum usado tanto para o preview quanto para impressão
     * 
     */
    protected function css_base(){
        ?>
        <style type="text/css" id="css-base">
        /* CSS common, both print and preview */
        #swp-print-orders {
            font-family: arial, sans-serif;
            font-size: 10.5pt;
        }
        
        .paper {
            background-color: #fff;
            width: <?php echo $this->paper['width']; ?>mm;
            height: <?php echo $this->paper['height']; ?>mm;
            margin: 10px auto;
            box-sizing: border-box;
            padding: <?php echo $this->layout['page_margins']; ?>;
        }
        
        .order {
            float: left;
            position: relative;
            width: <?php echo $this->layout['width']; ?>;
            height: <?php echo $this->layout['height']; ?>;
            margin: <?php echo $this->layout['item_margin']; ?>;
            position: relative;
        }
        
        .order-inner {
            padding: 2mm;
            position: relative;
        }
        
        .barcode {
            display: inline-block;
            font-size: 10pt;
            text-align: center;
            line-height: 11pt;
        }
        
        .aviso {
            border: 2px solid #000;
            text-align:center;
            font-size: 8pt;
            clear: both;
            padding: 0 5px;
            width: 110px;
        }

        .aviso div {
            margin: 8px 0;
        }
        
        .empty {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .empty span {
            padding: 4mm;
            border: 1px dashed #000;
            border-radius: 2mm;
        }
        
        hr {
            clear: both;
            display: block;
            margin: 0;
            visibility: hidden;
            width: 100%;
        }
        
        .invoice {
            padding: 10mm;
        }

        .invoice .invoice-logo {
            font-size: 18px;
        }
        
        .invoice .correios-logo {
            max-width: 40mm;
        }
        
        .invoice h1 {
            font-size: 18pt;
            margin: 0;
        }
        
        .invoice table {
            border: 2px solid #000;
            border-collapse: collapse;
            margin: 2mm 0;
            width: 100%;
        }
        
        .invoice table th {
            background-color: #d9d9d9;
            border: 1px solid #000;
            font-weight: bold;
            padding: 2mm;
            text-align: center;
        }
        
        .invoice table th.label {
            background-color: transparent;
        }
        
        .invoice table td {
            border: 1px solid #000;
            padding: 1mm;
        }
        
        .invoice table td.label {
            font-weight: bold;
            text-align: center;
        }
        
        .invoice table td .label {
            font-size: 80%;
        }

        .invoice table .label-right {
            text-align: right;
        }

        .invoice table tr.total td {
            font-weight: bold;
        }

        td.document {
            width: 60mm;
        }
        
        .invoice table.invoice-disclaimer td,
        .invoice table.invoice-obs td {
            font-size: 9.5pt;
            padding: 2mm;
        }
        
        .invoice table.invoice-disclaimer .text {
            text-indent: 12mm;
        }
        
        .invoice .signature-date {
            border: none;
            margin: 0 auto;
            padding: 0;
            width: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .invoice .signature-date .signature {
            border-top: 1px solid;
            margin-top: 30px;
            padding-top: 5px;
            text-align: center;
            width: 100mm;
        }
        
        .invoice table.invoice-obs ol {
            list-style-type: upper-roman;
            margin: 1mm 0 0;
            padding: 0 0 0 3mm;
        }
        
        .invoice table.invoice-obs ol li {
            padding: 0;
        }

        /**
         * Etiqueta 2x2
         * 
         */
        /* esconder botão sender */
        #print-btn-sender {
            display: none;
        }

        .paper {
            padding: 5mm;
        }

        .correios-blank {
            height: 60mm;
            overflow: hidden;
            position: relative;
        }
        .correios-blank .inner {
            color: #aaa;
            padding: 2mm;
            font-size: 8pt;
            line-height: 11pt;
        }
        .correios-blank .inner .declarado {
            font-size: 10px;
        }
        .correios-blank .inner .product {
            margin-bottom: 0.35mm;
            float: left;
            font-size: 7pt;
            width: 50%;
        }
        .correios-blank .inner .product strong {
            font-size: 12px;
        }
        .correios-blank .inner div.order-number {
            font-size: 9pt;
            float: none;
            width: 100%;
        }
        .correios-blank .order-items {
            column-count: 2;
            column-gap: 2mm;
            height: 50mm;
            column-fill: auto;
        }
        .bd {
            position: absolute;
            height: 10mm;
            width: 10mm;
        }
        #bd-tl {
            border-top: 0.8mm solid #000;
            border-left: 0.8mm solid #000;
            top: 0;
            left: 0;
        }
        #bd-tr {
            border-top: 0.8mm solid #000;
            border-right: 0.8mm solid #000;
            top: 0;
            right: 0;
        }
        #bd-bl {
            border-bottom: 0.8mm solid #000;
            border-left: 0.8mm solid #000;
            bottom: 0;
            left: 0;
        }
        #bd-br {
            border-bottom: 0.8mm solid #000;
            border-right: 0.8mm solid #000;
            bottom: 0;
            right: 0;
        }
        .assinatura-box {
            position: relative;
            font-size: 9pt;
            line-height: 9pt;
        }
        .assinatura-row {
            display: flex;
            align-items: baseline;
        }
        .assinatura-row div {
            margin-top: 10px;
        }
        .assinatura-row .line {
            content: '';
            flex: 1;
            margin: 0 2pt;
            height: 1px;
            background-color: #000;
        }
        .destinatario {
            clear: both;
            font-size: 10.5pt;
            overflow: hidden;
            padding: 2mm 0 0;
            position: relative;
            text-transform: uppercase;
        }
        .destinatario .destinatario-label {
            display: block;
            width: 24mm;
        }
        .images {
            position: relative;
            display: flex;
            justify-content: space-between;
        }
        .destinatario .shipping-method {
            border: 2px solid #000;
            border-radius: 6px;
            width: 30mm;
            height: 11mm;
            padding: 2mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .destinatario .shipping-method img {
            max-height: 18px;
            width: auto;
            align-self: center;
        }
        .destinatario .shipping-local-pickup {

        }
        .destinatario .shipping-local-pickup > div {
            display: flex;
            height: 100%;
            justify-content: center;
            align-items: center;
            text-align: center;
            font-weight: bold;
        }
        .destinatario .address {
            font-size: 8.5pt;
            height: 24mm;
            padding: 0 0 2mm;
            line-height: 11pt;
        }
        .destinatario .address .name {
            font-weight: bold;
        }
        .destinatario .address .city {
            white-space: nowrap;
        }
        .destinatario .barcode {
            float: left;
            overflow: hidden;
            text-align: center;
        }
        .remetente {
            font-size: 7pt;
            padding-top: 1mm;
            position: relative;
            display: flex;
            line-height: 9.5pt;
        }
        .remetente .address {
            flex: 1;
        }
        .remetente .shop-logo {
            font-size: 6pt;
            text-align: center;
            width: 26mm;
        }
        .remetente .shop-logo img {
            width: 100%;
        }
        .remetente .shop-logo .shop-logo-text {
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow-wrap: anywhere;
            border: 1px solid #000;
        }
        .remetente .zip {
            font-size: 120%;
            font-weight: bold;
        }
        .logo {
            float: right;
        }
        .logo img {
            max-width: 40mm;
            margin: 2mm 0;
        }
        .aviso-impresso {
            font-size: 6.5pt;
            border: none;
            padding: 1.2mm;
            width: auto;
        }
        .aviso-impresso div {
            margin: 1.5mm 0;
        }
        </style>
        <?php
        echo '<style type="text/css">' . $this->config['css']['base'] . '</style>';
    }
    
    /**
     * CSS exclusivo para preview
     * 
     */
    protected function css_preview(){
        ?>
        <style type="text/css" id="css-preview">
        /* CSS preview only */
        
        .paper {
            outline: 1px dashed green;
        }
        
        .order {
            outline: 1px dashed red;
        }

        .print-config-form .fieldsets {
            display: flex;
            gap: 10px;
        }

        .preview-label {
            text-align: center;
        }

        #swp-print-orders h3 {
            margin-bottom: 0;
        }

        #swp-print-orders h3 + p {
            margin-top: 0;
        }
        
        #swp-print-orders fieldset {
            border: 1px solid #0085ba;
            margin: 0;
            padding: 10px;
            flex: 1;
            min-width: 45%;
        }
        
        #swp-print-orders fieldset p {
            margin: 0;
        }
        
        #swp-print-orders .btn-print {
            background-color: green;
            font-size: 18px;
            padding: 4px 14px 1px
        }
        
        </style>
        <?php
        echo '<style type="text/css">' . $this->config['css']['preview'] . '</style>';
    }
    
    /**
     * CSS exclusivo para impressão
     * 
     */
    protected function css_print(){

        ?>
        <style type="text/css" id="css-print">
        /* CSS print only */
        @page {
            size: <?php echo $this->paper['name']; ?>;
            margin: 0;
        }
        @media print {
            /* É vital que as medidas do body sejam iguais ao tamanho do papel, para não ocorrer redimensionamento no navegador */
            html, body {
                height: <?php echo $this->paper['height']; ?>mm;
                margin: 0;
                width: <?php echo $this->paper['width']; ?>mm;
            }
            
            .paper {
                height: auto !important;
                width: auto !important;
                margin: auto !important;
                padding: auto !important;
                margin: 0;
                border: initial;
                border-radius: initial;
                width: initial;
                min-height: initial;
                box-shadow: initial;
                background: initial;
                page-break-after: always;
                overflow: hidden;
                outline: none;
            }
            
            .order {
                outline: 1px dotted #ccc;
                outline: none;
            }
            
            .empty span {
                display: none;
            }

            .inner {
                color: #eee !important;
            }
            
            .no-print {
                display: none;
            }
            
            .empty-data {
                display: none;
            }

            html, body {
                margin: 0 !important;
                padding: 0 !important;
                height: auto !important;
                width: auto !important;
            }
            #wpcontent,
            #wpbody-content,
            #swp-print-orders {
                margin: 0 !important;
                padding: 0 !important;
                height: auto !important;
                width: auto !important;
                float: none !important;
            }

            #adminmenumain,
            #wpadminbar {
                display: none;
            }
        }
        
        <?php echo $this->config['css']['print']; ?>
        
        </style>
        <?php
    }
}



/**
 * Classe base para cada etiqueta individual
 * 
 */
abstract class SWP_Print_Order_Label {
    
    protected $shop_data         = array();

    protected $shop_logo         = '';
    protected $logo_sedex        = '';
    protected $logo_pac          = '';
    protected $method_img        = '';
    protected $cost              = '';
    protected $cost_display      = '';
    
    protected $address;
    protected $barcode;
    protected $label;
    protected $order;

    protected $assets;
    
    public function __construct( $order, $address, $barcode ){
        $this->url = plugins_url( '/', __FILE__ );

        $this->label_destinatario = $this->url . 'assets/img/label-destinatario.png';
        $this->logo_correios      = $this->url . 'assets/img/etiqueta-logo-correios.png';
        $this->logo_sedex         = $this->url . 'assets/img/etiqueta-logo-correios-sedex.png';
        $this->logo_pac           = $this->url . 'assets/img/etiqueta-logo-correios-pac.png';
        
        $this->order   = $order;
        $this->address = $address;
        $this->barcode = $barcode;
        
        $this->set_shop_data();
        $this->set_shop_logo();
        $this->set_method_image();
        $this->set_label();
    }
    
    public function get_label(){
        return $this->label;
    }

    public function set_shop_data(){
        $shop_keys = array(
            'blogname',
            'woocommerce_store_address',
            'woocommerce_store_address_2',
            'woocommerce_store_city',
            'woocommerce_default_country',
            'woocommerce_store_postcode',
            'woocommerce_store_cpf_cnpj',
        );
        foreach( $shop_keys as $key ){
            $opt = get_option($key);
            if( $key == 'woocommerce_default_country' ){
                $opt = explode(':', $opt)[1];
            }
            $this->shop_data[$key] = $opt;
        }
    }

    protected function set_shop_logo(){
        if( !empty($this->shop_logo) ){
            $this->shop_logo = "<img class='shop-logo-img' src='{$this->shop_logo}' alt='' />";
        }
        else{
            $this->shop_logo = sprintf('<div class="shop-logo-text">%s</div>', get_bloginfo('name'));
        }
    }

    abstract protected function set_label();
    
    protected function get_order_cost( $order_id ){
        
        $order = wc_get_order( $order_id );
        $subtotal = $subtotal_taxes = 0;

        foreach( $order->get_items() as $item ){
            $subtotal += (double) $item->get_subtotal();
        }

        return $subtotal;
    }
    
    protected function set_method_image(){
        
        if( $this->has_shipping_method('correios-sedex') ){
            $this->method_img = "<div class='shipping-method shipping-sedex'><img src='{$this->logo_sedex}' alt='' /><img src='{$this->logo_correios}' alt='' /></div>";
        }
        elseif( $this->has_shipping_method('correios-pac') || $this->has_shipping_method('free') ){
            $this->method_img = "<div class='shipping-method shipping-pac'><img src='{$this->logo_pac}' alt='' /><img src='{$this->logo_correios}' alt='' /></div>";
        }
        elseif( $this->has_shipping_method('local_pickup') ){
            $this->method_img = "<div class='shipping-method shipping-local-pickup'><div>retirada</div></div>";
        }
    }
    
    protected function has_shipping_method( $method_id ) {
        foreach ( $this->order->get_shipping_methods() as $shipping_method ) {
            $pos = strpos( $shipping_method['method_id'], $method_id );
            if ( $pos !== false ) {
                return true;
            }
        }
        return false;
    }
}

/**
 * Modelo de etiqueta 2x2
 * 
 */
class SWP_Print_Order_Label_2x2 extends SWP_Print_Order_Label {

    protected function set_label(){

        $order_id = $this->order->get_id();
        $this->cost = $this->get_order_cost( $this->order->get_id() );
        $this->cost_display = wc_price( $this->cost );
        
        // itens do pedido
        $items = $this->order->get_items();
        $cart = array();
        foreach( $items as $id => $product ){
            $product_data = $product->get_data();
            $product_sku  = get_post_meta( $product_data['product_id'], '_sku', true );
            $sku          = empty($product_sku) ? '' : "[{$product_sku}]";
            $cart[] = "<strong>({$product_data['quantity']})</strong> - {$sku}{$product['name']}";
        }
        $cart_count = count($cart);
        $cart = implode('</div><div>', $cart);
        
        $customer_note = $this->order->get_customer_note();
        if( !empty($customer_note) ){
            $customer_note = "<div class='customer-note'>{$customer_note}</div>";
        }
        
        $order_list = "<div class='order-number'>PEDIDO #{$this->order->get_id()}</div><div class='order-items'><div>{$cart}</div></div>";
        
        $this->label = "
        <div class='order-inner'>
            <div class='correios-blank'>
                <div class='bd' id='bd-tl'></div>
                <div class='bd' id='bd-tr'></div>
                <div class='bd' id='bd-bl'></div>
                <div class='bd' id='bd-br'></div>
                <div class='inner'>
                    <div class='declarado'>Valor declarado: {$this->cost_display}</div>
                    {$order_list}
                </div>
            </div>
            <div class='assinatura-box'>
                <div class='assinatura-row'>
                    <div>Recebedor:</div>
                    <span class='line'></span>
                </div>
                <div class='assinatura-row'>
                    <div class='assinatura'>Assinatura:</div>
                    <span class='line'></span>
                    <div class='documento'>Documento:</div>
                    <span class='line'></span>
                </div>
            </div>
            <div class='destinatario'>
                <div class='address'>
                    <img class='destinatario-label' src='{$this->label_destinatario}' alt='' />
                    <span class='name'>{$this->address['nome']}</span> <span class='company'>{$this->address['empresa']}</span><br />
                    <span class='street'>{$this->address['logradouro']}{$this->address['complemento']}</span><br />
                    <span class='neighbor'>{$this->address['bairro']}</span>
                    <br /><span class='city'>{$this->address['cidade']}</span> / <span class='state'>{$this->address['uf']}</span>
                </div>
                <div class='images'>
                    <div class='barcode'>
                        {$this->address['cep']} {$this->address['uf']}<br />
                        <img src='data:image/png;base64,{$this->barcode}' />
                    </div>
                    {$this->method_img}
                </div>
            </div>
            <div class='remetente'>
                <div class='address'>
                    <strong>Remetente:<br /></strong>
                    <span class='name'>{$this->shop_data['blogname']}<br /></span> 
                    <span class='street'>{$this->shop_data['woocommerce_store_address']}<br /></span> 
                    <span class='neighbor'>{$this->shop_data['woocommerce_store_address_2']}<br /></span>
                    <span class='zip'>{$this->shop_data['woocommerce_store_postcode']}</span> 
                    <span class='city-state'>{$this->shop_data['woocommerce_store_city']} {$this->shop_data['woocommerce_default_country']}<br /></span>
                </div>
                <div class='shop-logo'>
                    {$this->shop_logo}
                </div>
            </div>
        </div>";
    }
}


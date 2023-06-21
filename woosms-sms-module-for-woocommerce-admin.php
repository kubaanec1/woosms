<?php declare(strict_types=1);

/**
 * Back office plugin
 * PHP version 7.4
 *
 * @category BulkGate Plugin
 * @package  BulkGate
 * @author   Lukáš Piják <pijak@bulkgate.com>
 * @license  GNU General Public License v3.0
 * @link     https://www.bulkgate.com/
 */

use BulkGate\WooSms\{Ajax\Authenticate, Ajax\PluginSettingsChange, DI\Factory, Utils\Escape, Utils\Meta};
use BulkGate\Plugin\{Eshop, IO, Settings\Settings, User, User\Sign, Utils\JsonResponse};

if (!defined('ABSPATH'))
{
    exit;
}

add_action('admin_menu', function (): void
{
    add_menu_page('bulkgate', 'BulkGate SMS', 'manage_options', 'bulkgate', function (): void
    {
	    Factory::get()->getByClass(Eshop\EshopSynchronizer::class)->run();

	    Woosms_Print_widget();

        echo <<<'HTML'
            <style>
                #woo-sms {
                    margin-left: calc(var(--woosms-body-indent, 0) * -1);
                }
                ecommerce-module {
                    box-sizing: border-box; /* realne se tyka pouze web-componenty */
                }
            </style>
        HTML;
        echo <<<HTML
            <div id="woo-sms" style="--primary: #955a89; --secondary: #0094F0; --content: #f1f1f1;">
                <ecommerce-module>
                    TODO: loading app
                </ecommerce-module>
            </div>
        HTML;
    }, 'dashicons-email-alt', 58);
    add_filter('plugin_action_links', [Meta::class, 'settingsLink'], 10, 2);
    add_filter('plugin_row_meta', [Meta::class, 'links'], 10, 2);
});

add_action('wp_ajax_authenticate', fn () => Factory::get()->getByClass(Authenticate::class)->run(admin_url('admin.php?page=bulkgate#/sign/in')));

add_action('wp_ajax_login', fn () => JsonResponse::send(Factory::get()->getByClass(Sign::class)->in(
	sanitize_text_field((string) ($_POST['__bulkgate']['email'] ?? '')),
	sanitize_text_field((string) ($_POST['__bulkgate']['password'] ?? '')),
	admin_url('admin.php?page=bulkgate#/dashboard')
)));

add_action('wp_ajax_logout_module', fn () => JsonResponse::send(Factory::get()->getByClass(Sign::class)->out(admin_url('admin.php?page=bulkgate#/sign/in'))));
add_action('wp_ajax_save_module_settings', fn () => JsonResponse::send(Factory::get()->getByClass(PluginSettingsChange::class)->run($_POST['__bulkgate'] ?? [])));

add_action(
    'add_meta_boxes', function ($post_type) {

        if ($post_type === 'shop_order' && Factory::get()->getByClass(Settings::class)->load('static:application_token'))
		{
			add_meta_box('bulkgate_send_message', 'BulkGate SMS', fn () => print("TODO SEND MESSAGE FORM"), 'shop_order', 'side', 'high');
            /*add_meta_box(
                'send_sms', 'BulkGate', function ($post) {
                    ?><div id="woo-sms" style="margin:0; zoom: 0.85">
            <div id="react-snack-root" style="zoom: 0.8"></div>
            <div id="react-app-root">
                    <?php echo Escape::html(woosms_translate('loading_content', 'Loading content')); ?>
            </div>
                    <?php
                    Woosms_Print_widget('ModuleComponents', 'sendSms', ['id' => get_post_meta($post->ID, '_billing_phone', 'true'), 'key' => strtolower(get_post_meta($post->ID, '_billing_country', 'true'))]);
                    ?></div><?php
                }, 'shop_order', 'side', 'high'
            );*/
        }
    }
);



function Woosms_Print_widget(): void
{
	$di = Factory::get();

	$url = admin_url('/admin-ajax.php', is_ssl() ? 'https' : 'http');

	$proxy = [
		'PROXY_LOG_IN' => [
			'url' => $url,
			'params' => ['action' => 'login']
		],
		'PROXY_LOG_OUT' => [
			'url' => $url,
			'params' => ['action' => 'logout_module']
		],
		'PROXY_SAVE_MODULE_SETTINGS' => [
			'url' => $url,
			'params' => ['action' => 'save_module_settings']
		]
	];

	$settings = $di->getByClass(Settings::class);

	$plugin_settings = [
		'dispatcher' => $settings->load('main:dispatcher') ?? 'cron',
		'synchronization' => $settings->load('main:synchronization') ?? 'all',
		'language' => $settings->load('main:language') ?? 'en',
		'language_mutation' => $settings->load('main:language_mutation') ?? 0,
		'delete_db' => $settings->load('main:delete_db') ?? 0
	];

    $url = $di->getByClass(IO\Url::class);
    $user = $di->getByClass(User\Sign::class);
    $jwt = $user->authenticate();

    $escape_js = [Escape::class, 'js'];

    wp_print_inline_script_tag(
        <<<JS
            function initWidget_ecommerce_module(widget) {
                function getHeaders(token) {
                    return function () {
                        return {
                            Authorization: "Bearer " + token
                        }
                    }
                }
                widget.merge({
                    layout: {
                        server: {
                            application_settings: {$escape_js($plugin_settings)}
                        },
                        // static (dictionary) for frontend form
                        scope: {
                            application_settings: {
                                dispatcher: {cron: "dispatcher_cron", asset: "dispatcher_asset", direct: "dispatcher_direct"},
                                synchronization: {all: "sync_all", message: "sync_message"}
                            }
                        }
                    }
                });
                widget.authenticator = {
                    getHeaders: getHeaders({$escape_js($jwt)}),
                    setToken: (token) => {
                        widget.authenticator.getHeaders = getHeaders(token);
                    },
                    authenticate: async () => {
                        let response = await fetch(ajaxurl, {
                            method: "POST",
                            headers: {
                                'Content-Type': "application/x-www-form-urlencoded"
                            },
                            body: "action=authenticate",
                        });
                        let {token, redirect} = await response.json();
                        
                        if (redirect){
                            return {redirect};
                        }
                        if (token) {
                            widget.authenticator.getHeaders = getHeaders(token);
                        }
                        
                        return {};
                    }
                };
                widget.events.onComputeHostLayout = (compute) => {
                    let hostAppBar = document.getElementById("wpadminbar");
                    let hostNavBar = document.getElementById("adminmenuback");
                    let hostRootWrap = document.getElementById("woo-sms");
                    
                    compute({appBar: hostAppBar, navBar: hostNavBar});
                    
                    if (hostRootWrap.parentElement.id === "wpbody-content") { // woosms-module page, otherwise eg. send-sms widget
                        let style = getComputedStyle(document.getElementById("wpcontent"));
                        hostRootWrap.style.setProperty("--woosms-body-indent", style.getPropertyValue("padding-left"));
                    }
                };
                
                widget.options.main = {
                    //sign:in
                    showLanguagePanel: false,
                    showPermanentLogin: false,
                    logo: "images/white-label/bulkgate/logo/logo-title.svg",
                    logo_dark: "images/white-label/bulkgate/logo/logo-white.svg",
                    background: "images/products/backgrounds/ws.svg"
                };
                widget.options.layout = {
                    appBar: {
                        showLogOut: false,
                        logoUrl: "images/products/bg.svg",
                        logoStyle: {
                            height: "40px",
                            width: "100px",
                        }
                    }
                };
                widget.options.proxy = function(reducerName, requestData) {
                    let proxyData = {$escape_js($proxy)};
                    let {url, params} = proxyData[requestData.actionType] || {};

                    if (url) {
                        requestData.contentType = "application/x-www-form-urlencoded";
                        requestData.url = url;
                        requestData.data = {__bulkgate: requestData.data, ...params};
                        return true;
                    }
                    
                    try {
                        // relative -> absolute url conversion. In modules context, relative urls are not suitable. This covers routing (soft redirects change route) and signals (actions).
                        let baseUrl = new URL({$escape_js($url->get())}); // bulkgate's app url
                        url = new URL(requestData.url, baseUrl);
                        requestData.url = url.toString();
                        return true;
                    } catch {}
                };
                
                console.log("configuration called", widget);
            }
    JS);

    wp_print_script_tag([
        'src' => Escape::url($url->get("widget/eshop/load/$jwt?config=initWidget_ecommerce_module")),
        'async' => true,
    ]);
}

<?php
namespace Gm2;

if (!defined('ABSPATH')) {
    exit;
}

class AE_SEO_Server_Hints {
    public function run(): void {
        add_action('admin_menu', [ $this, 'add_page' ]);
    }

    public function add_page(): void {
        add_management_page(
            __('AE Server Hints', 'gm2-wordpress-suite'),
            __('AE Server Hints', 'gm2-wordpress-suite'),
            'manage_options',
            'ae-seo-server-hints',
            [ $this, 'render_page' ]
        );
    }

    private function get_apache_snippet(): string {
        return <<<APACHE
<IfModule mod_headers.c>
    <FilesMatch "\.(js|css|png|jpe?g|gif|svg|webp|avif)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>
    <FilesMatch "\.js$">
        Header append Cache-Control "no-transform"
    </FilesMatch>
</IfModule>
<IfModule mod_brotli.c>
    AddOutputFilterByType BROTLI_COMPRESS text/plain text/css application/javascript application/json image/svg+xml
</IfModule>
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain text/css application/javascript application/json image/svg+xml
</IfModule>
APACHE;
    }

    private function get_nginx_snippet(): string {
        return <<<NGINX
gzip on;
gzip_types text/plain text/css application/javascript application/json image/svg+xml;
brotli on;
brotli_types text/plain text/css application/javascript application/json image/svg+xml;

location ~* \.(js|css|png|jpe?g|gif|svg|webp|avif)$ {
    add_header Cache-Control "public, max-age=31536000, immutable";
}
location ~* \.js$ {
    add_header Cache-Control "no-transform";
}
NGINX;
    }

    private function htaccess_writable(): bool {
        $file = ABSPATH . '.htaccess';
        if (file_exists($file)) {
            return is_writable($file);
        }
        return is_writable(ABSPATH);
    }

    public function render_page(): void {
        $apache = $this->get_apache_snippet();
        $nginx = $this->get_nginx_snippet();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'AE Server Hints', 'gm2-wordpress-suite' ) . '</h1>';

        $default  = includes_url('js/jquery/jquery.min.js');
        $endpoint = esc_url_raw(rest_url('ae-seo/v1/diag/headers'));
        $nonce    = wp_create_nonce('wp_rest');
        echo '<h2>' . esc_html__( 'Check Asset Headers', 'gm2-wordpress-suite' ) . '</h2>';
        echo '<p><input type="text" id="ae-seo-header-url" size="80" value="' . esc_attr($default) . '" /> ';
        echo '<button type="button" class="button" id="ae-seo-header-check">' . esc_html__( 'Check', 'gm2-wordpress-suite' ) . '</button></p>';
        echo '<pre id="ae-seo-header-result"></pre>';
        echo '<script>';
        echo 'document.getElementById("ae-seo-header-check").addEventListener("click",function(){';
        echo 'var url=document.getElementById("ae-seo-header-url").value;';
        echo 'var out=document.getElementById("ae-seo-header-result");';
        echo 'out.textContent="Checking...";';
        echo 'fetch("' . esc_js($endpoint) . '?url="+encodeURIComponent(url),{headers:{"X-WP-Nonce":"' . esc_js($nonce) . '"}})';
        echo '.then(function(r){return r.json();}).then(function(data){';
        echo 'var text="";';
        echo 'if(data.headers){for(var k in data.headers){text+=k+": "+data.headers[k]+"\n";}}';
        echo 'else if(data.message){text=data.message;}';
        echo 'else{text=JSON.stringify(data);}';
        echo 'out.textContent=text;';
        echo '}).catch(function(err){out.textContent="Error: "+err;});';
        echo '});';
        echo '</script>';

        if (is_apache() && isset($_POST['ae_seo_write_htaccess']) && check_admin_referer('ae_seo_write_htaccess')) {
            $file = ABSPATH . '.htaccess';
            if ($this->htaccess_writable()) {
                file_put_contents($file, PHP_EOL . $apache . PHP_EOL, FILE_APPEND);
                echo '<div class="updated"><p>' . esc_html__( '.htaccess updated.', 'gm2-wordpress-suite' ) . '</p></div>';
            } else {
                echo '<div class="error"><p>' . esc_html__( 'Unable to write .htaccess.', 'gm2-wordpress-suite' ) . '</p></div>';
            }
        }

        echo '<h2>' . esc_html__( 'Apache', 'gm2-wordpress-suite' ) . '</h2>';
        echo '<textarea rows="15" style="width:100%;">' . esc_textarea($apache) . '</textarea>';
        if (is_apache() && $this->htaccess_writable()) {
            echo '<form method="post">';
            wp_nonce_field('ae_seo_write_htaccess');
            echo '<p><input type="submit" name="ae_seo_write_htaccess" class="button button-primary" value="' . esc_attr__( 'Write .htaccess', 'gm2-wordpress-suite' ) . '"></p>';
            echo '</form>';
        }

        echo '<h2>' . esc_html__( 'Nginx', 'gm2-wordpress-suite' ) . '</h2>';
        echo '<textarea rows="15" style="width:100%;">' . esc_textarea($nginx) . '</textarea>';
        echo '</div>';
    }
}

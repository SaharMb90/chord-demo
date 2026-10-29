<?php
/**
 * Plugin Name: Chord Demo
 * Description: نوع محتوای «آهنگ»، طبقه‌بندی «خواننده»، نمایش آکورد بالای ترانه و تغییر گام.
 * Version: 0.1
 */
if (!defined('ABSPATH')) exit;

/* ۱) نوع محتوای آهنگ و طبقه‌بندی خواننده */
add_action('init', function () {
    register_post_type('song', [
        'labels' => ['name' => 'آهنگ‌ها', 'singular_name' => 'آهنگ', 'add_new' => 'افزودن آهنگ',
                     'add_new_item' => 'افزودن آهنگ جدید', 'edit_item' => 'ویرایش آهنگ', 'search_items' => 'جستجوی آهنگ'],
        'public' => true, 'has_archive' => true, 'menu_icon' => 'dashicons-format-audio',
        'supports' => ['title', 'thumbnail'], 'rewrite' => ['slug' => 'song'],
    ]);
    register_taxonomy('singer', 'song', [
        'labels' => ['name' => 'خواننده‌ها', 'singular_name' => 'خواننده', 'add_new_item' => 'افزودن خواننده'],
        'public' => true, 'hierarchical' => false, 'show_admin_column' => true,
        'rewrite' => ['slug' => 'singer'],
    ]);
});
register_activation_hook(__FILE__, function () { do_action('init'); flush_rewrite_rules(); });

/* ۲) کادر ورود آکورد در پنل مدیریت */
add_action('add_meta_boxes', function () {
    add_meta_box('chord_text', 'متن و آکورد (مثال: [Am]باران [G]می‌بارد)', function ($post) {
        wp_nonce_field('chord_save', 'chord_nonce');
        $v = get_post_meta($post->ID, '_chord_text', true);
        echo '<textarea name="chord_text" style="width:100%;min-height:320px;direction:rtl;font-size:15px">' . esc_textarea($v) . '</textarea>';
    }, 'song', 'normal', 'high');
});
add_action('save_post_song', function ($id) {
    if (!isset($_POST['chord_nonce']) || !wp_verify_nonce($_POST['chord_nonce'], 'chord_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $id)) return;
    update_post_meta($id, '_chord_text', sanitize_textarea_field(wp_unslash($_POST['chord_text'] ?? '')));
});

/* ۳) تبدیل [Am]متن به HTML (سمت سرور، برای سئو و سرعت) */
function chord_demo_render($text) {
    $out = '';
    foreach (preg_split('/\r\n|\n/', $text) as $line) {
        if (trim($line) === '') { $out .= '<div class="cd-ln cd-empty"></div>'; continue; }
        $parts = preg_split('/\[([^\]]+)\]/u', $line, -1, PREG_SPLIT_DELIM_CAPTURE);
        $out .= '<div class="cd-ln">';
        $chord = '';
        foreach ($parts as $i => $p) {
            if ($i % 2 === 1) { $chord = $p; continue; }
            if ($p === '' && $chord === '') continue;
            $t = $p === '' ? '&nbsp;' : esc_html($p);
            $out .= '<span class="cd-seg"><span class="cd-c" data-chord="' . esc_attr($chord) . '">'
                  . esc_html($chord) . '</span><span class="cd-t">' . $t . '</span></span>';
            $chord = '';
        }
        $out .= '</div>';
    }
    return $out;
}

/* ۴) نمایش در صفحه آهنگ */
add_filter('the_content', function ($content) {
    if (!is_singular('song') || !in_the_loop() || !is_main_query()) return $content;
    $id = get_the_ID();
    $singers = get_the_term_list($id, 'singer', '<p class="cd-singer">خواننده: ', '، ', '</p>');
    $bar = '<div class="cd-bar"><button type="button" data-step="-1">− نیم‌پرده</button>'
         . '<span class="cd-key">0</span><button type="button" data-step="1">+ نیم‌پرده</button>'
         . '<button type="button" data-step="0" class="cd-ghost">گام اصلی</button></div>';
    return $content . ($singers ?: '') . $bar
         . '<div class="cd-sheet">' . chord_demo_render(get_post_meta($id, '_chord_text', true)) . '</div>';
});

/* ۵) CSS و JS فقط در صفحه آهنگ */
add_action('wp_enqueue_scripts', function () {
    if (!is_singular('song')) return;
    wp_register_style('chord-demo', false); wp_enqueue_style('chord-demo');
    wp_add_inline_style('chord-demo', '
.cd-bar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:12px 0}
.cd-bar button{padding:6px 14px;border:0;border-radius:6px;background:#1D2724;color:#fff;cursor:pointer}
.cd-bar .cd-ghost{background:transparent;color:inherit;border:1px solid #ccc}
.cd-key{direction:ltr;font-weight:700;min-width:3em;text-align:center}
.cd-sheet{direction:rtl;font-size:1.1rem}
.cd-ln{margin:0 0 6px;line-height:1}.cd-empty{height:1.2em}
.cd-seg{display:inline-block;vertical-align:bottom;white-space:pre}
.cd-c{display:block;direction:ltr;text-align:right;color:#0B6B66;font-weight:700;font-size:.85em;min-height:1.3em;padding-left:.4em}
.cd-t{display:block;line-height:1.8}');
    wp_register_script('chord-demo', false, [], null, true); wp_enqueue_script('chord-demo');
    wp_add_inline_script('chord-demo', '
(function(){
 var S=["C","C#","D","D#","E","F","F#","G","G#","A","A#","B"],F=["C","Db","D","Eb","E","F","Gb","G","Ab","A","Bb","B"],
     P=["C","C#","D","Eb","E","F","F#","G","Ab","A","Bb","B"],off=0;
 function sh(n,k){var i=S.indexOf(n);if(i<0)i=F.indexOf(n);return i<0?n:P[((i+k)%12+12)%12];}
 function tr(c,k){var m=c.match(/^([A-G][#b]?)([^\/]*)(?:\/([A-G][#b]?))?$/);return m?sh(m[1],k)+m[2]+(m[3]?"/"+sh(m[3],k):""):c;}
 document.addEventListener("click",function(e){
  var b=e.target.closest(".cd-bar button");if(!b)return;
  var s=+b.dataset.step;off=s===0?0:(off+s)%12;
  document.querySelectorAll(".cd-c").forEach(function(el){if(el.dataset.chord)el.textContent=tr(el.dataset.chord,off);});
  document.querySelector(".cd-key").textContent=off>0?"+"+off:off;
 });
})();');
});

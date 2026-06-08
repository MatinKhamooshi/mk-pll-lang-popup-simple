<?php
/**
 * Plugin Name: MK Polylang Language Popup (Simple)
 * Description: نمایش پاپ‌آپ انتخاب زبان در هر بازدید از صفحه اصلی، بدون کوکی. انتخاب زبان پیش‌فرضِ همین صفحه فقط پاپ‌آپ را می‌بندد؛ سایر زبان‌ها ریدایرکت می‌شوند.
 * Version: 1.0.0
 * Author: Matin Khamooshi
 * Author URI: https://matinkhamooshi.ir
 * Text Domain: mk-pll-lang-popup-simple
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

class MK_PLL_Lang_Popup_Simple
{

    /**
     * کش لیست زبان‌ها در طول یک ریکوئست.
     *
     * @var array|null
     */
    private $languages = null;

    public function __construct()
    {
        add_action('wp_footer', array($this, 'render_popup'));
    }

    /**
     * آیا روی روتِ صفحه اصلی هستیم؟ (front page بدون صفحه‌بندی)
     *
     * @return bool
     */
    private function is_home_root()
    {
        return is_front_page() && !is_paged();
    }

    /**
     * دریافت لیست زبان‌های Polylang (با کش درون‌ریکوئستی).
     *
     * خروجی: [ slug => [ slug, name, flag, url ] ]
     *
     * @return array
     */
    private function get_languages()
    {
        if (null !== $this->languages) {
            return $this->languages;
        }

        $this->languages = array();

        if (!function_exists('pll_the_languages')) {
            return $this->languages;
        }

        $langs = pll_the_languages(
            array(
                'raw' => 1,
                'display_names_as' => 'name',
                'hide_if_no_translation' => 0,
            )
        );

        if (is_array($langs)) {
            foreach ($langs as $slug => $data) {
                $this->languages[$slug] = array(
                    'slug' => $slug,
                    'name' => isset($data['name']) ? $data['name'] : $slug,
                    'flag' => isset($data['flag']) ? $data['flag'] : '',
                    'url' => isset($data['url']) ? $data['url'] : home_url('/'),
                );
            }
        }

        return $this->languages;
    }

    /**
     * زبان فعلی صفحه.
     *
     * @return string
     */
    private function current_lang()
    {
        return function_exists('pll_current_language') ? (string) pll_current_language() : '';
    }

    /**
     * رندر پاپ‌آپ در فوتر (در هر بازدید از روتِ صفحه اصلی).
     *
     * @return void
     */
    public function render_popup()
    {
        if (!$this->is_home_root()) {
            return;
        }

        $langs = $this->get_languages();
        if (empty($langs)) {
            return;
        }

        $current = $this->current_lang();
        ?>
        <style>
            /* font-family عمداً تنظیم نشده تا از فونت قالب ارث‌بری شود */
            #mk-pll-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, .6);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 999999;
            }

            #mk-pll-popup {
                background: #fff;
                border-radius: 12px;
                padding: 30px 25px;
                max-width: 420px;
                width: 90%;
                text-align: center;
                box-shadow: 0 10px 40px rgba(0, 0, 0, .3);
            }

            #mk-pll-popup h3 {
                margin: 0 0 20px;
                font-size: 18px;
                color: #222;
            }

            .mk-pll-list {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                justify-content: center;
            }

            .mk-pll-item {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 10px 16px;
                border: 1px solid #e2e2e2;
                border-radius: 8px;
                cursor: pointer;
                color: #333;
                font-size: 15px;
                background: #fff;
                min-width: 120px;
                justify-content: center;
                transition: background .2s, border-color .2s, transform .2s;
            }

            .mk-pll-item:hover {
                background: #f5f5f5;
                border-color: #bbb;
                transform: translateY(-2px);
            }

            .mk-pll-flag {
                width: 22px;
                height: auto;
                display: inline-block;
            }
        </style>

        <div id="mk-pll-overlay">
            <div id="mk-pll-popup" role="dialog" aria-modal="true">
                <h3>
                    <?php esc_html_e('لطفاً زبان خود را انتخاب کنید', 'mk-pll-lang-popup-simple'); ?>
                </h3>
                <div class="mk-pll-list">
                    <?php foreach ($langs as $slug => $lang): ?>
                        <button type="button" class="mk-pll-item" data-lang="<?php echo esc_attr($slug); ?>"
                            data-url="<?php echo esc_url($lang['url']); ?>">
                            <?php
                            if (!empty($lang['flag'])) {
                                if (false !== strpos($lang['flag'], '<img')) {
                                    echo wp_kses_post($lang['flag']);
                                } else {
                                    printf(
                                        '<img class="mk-pll-flag" src="%1$s" alt="%2$s" />',
                                        esc_url($lang['flag']),
                                        esc_attr($lang['name'])
                                    );
                                }
                            }
                            ?>
                            <span class="mk-pll-name">
                                <?php echo esc_html($lang['name']); ?>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <script>
            (function () {
                var overlay = document.getElementById('mk-pll-overlay');
                var currLang = <?php echo wp_json_encode($current); ?>;
                if (!overlay) return;

                overlay.addEventListener('click', function (e) {
                    var btn = e.target.closest('.mk-pll-item');
                    if (!btn) return;

                    var lang = btn.getAttribute('data-lang');
                    var url = btn.getAttribute('data-url');

                    // اگر زبان انتخابی همان زبان صفحهٔ فعلی است (مثلاً زبان پیش‌فرض روی /)،
                    // فقط پاپ‌آپ بسته شود؛ نیازی به ریلود نیست.
                    if (lang === currLang) {
                        overlay.parentNode && overlay.parentNode.removeChild(overlay);
                    } else {
                        window.location.href = url;
                    }
                });
            })();
        </script>
        <?php
    }
}

new MK_PLL_Lang_Popup_Simple();
<?php
/**
 * JTDIS - Komponen logo global.
 *
 * Salin fail logo ke:
 * /jdtis_asset/assets/images/jtdi-logo.png
 */

if (!defined('JDTIS_BASE_URL')) {
    define('JDTIS_BASE_URL', '/jdtis_asset');
}

if (!function_exists('renderJtdiBrand')) {
    /**
     * Paparkan logo JTDI pada mana-mana halaman.
     *
     * Variant:
     * - sidebar: untuk sidebar berwarna gelap
     * - login: untuk kad log masuk
     * - header: untuk bahagian atas halaman
     * - compact: versi kecil
     */
    function renderJtdiBrand(
        string $variant = 'sidebar',
        string $subtitle = 'Sistem Pengurusan Aset ICT',
        string $href = ''
    ): void {
        $allowedVariants = [
            'sidebar',
            'login',
            'header',
            'compact',
        ];

        if (!in_array($variant, $allowedVariants, true)) {
            $variant = 'sidebar';
        }

        $imageUrl = JDTIS_BASE_URL . '/assets/images/jtdi-logo.png';

        if ($href === '') {
            $href = JDTIS_BASE_URL . '/pages/dashboard.php';
        }

        $settings = [
            'sidebar' => [
                'wrapper' => implode(';', [
                    'display:flex',
                    'flex-direction:column',
                    'align-items:center',
                    'justify-content:center',
                    'gap:8px',
                    'width:100%',
                    'margin-bottom:24px',
                    'text-decoration:none',
                    'color:#ffffff',
                ]),
                'panel' => implode(';', [
                    'width:100%',
                    'padding:8px 4px',
                    'background:transparent',
                    'border-radius:16px',
                    'text-align:center',
                ]),
                'image' => implode(';', [
                    'display:block',
                    'width:min(190px,100%)',
                    'height:auto',
                    'margin:0 auto',
                    'object-fit:contain',
                    'filter:drop-shadow(0 8px 18px rgba(0,0,0,.18))',
                ]),
                'subtitle' => implode(';', [
                    'margin-top:7px',
                    'font-size:12px',
                    'font-weight:600',
                    'letter-spacing:.02em',
                    'color:rgba(255,255,255,.72)',
                    'text-align:center',
                ]),
            ],
            'login' => [
                'wrapper' => implode(';', [
                    'display:block',
                    'width:100%',
                    'margin-bottom:22px',
                    'text-decoration:none',
                ]),
                'panel' => implode(';', [
                    'padding:18px 20px',
                    'background:linear-gradient(135deg,#172554,#0f172a)',
                    'border-radius:20px',
                    'box-shadow:0 16px 35px rgba(15,23,42,.18)',
                    'text-align:center',
                ]),
                'image' => implode(';', [
                    'display:block',
                    'width:min(330px,100%)',
                    'height:auto',
                    'margin:0 auto',
                    'object-fit:contain',
                ]),
                'subtitle' => implode(';', [
                    'margin-top:10px',
                    'font-size:13px',
                    'font-weight:700',
                    'letter-spacing:.025em',
                    'color:rgba(255,255,255,.82)',
                    'text-align:center',
                ]),
            ],
            'header' => [
                'wrapper' => implode(';', [
                    'display:inline-flex',
                    'align-items:center',
                    'text-decoration:none',
                    'margin-bottom:14px',
                ]),
                'panel' => implode(';', [
                    'display:inline-flex',
                    'align-items:center',
                    'gap:12px',
                    'padding:8px 14px',
                    'background:linear-gradient(135deg,#172554,#0f172a)',
                    'border-radius:14px',
                    'box-shadow:0 8px 22px rgba(15,23,42,.14)',
                ]),
                'image' => implode(';', [
                    'display:block',
                    'width:170px',
                    'max-width:100%',
                    'height:auto',
                    'object-fit:contain',
                ]),
                'subtitle' => implode(';', [
                    'display:none',
                ]),
            ],
            'compact' => [
                'wrapper' => implode(';', [
                    'display:inline-flex',
                    'align-items:center',
                    'text-decoration:none',
                ]),
                'panel' => implode(';', [
                    'display:inline-flex',
                    'align-items:center',
                    'padding:6px 10px',
                    'background:linear-gradient(135deg,#172554,#0f172a)',
                    'border-radius:12px',
                ]),
                'image' => implode(';', [
                    'display:block',
                    'width:120px',
                    'max-width:100%',
                    'height:auto',
                    'object-fit:contain',
                ]),
                'subtitle' => implode(';', [
                    'display:none',
                ]),
            ],
        ];

        $style = $settings[$variant];
        $safeHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
        $safeImage = htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8');
        $safeSubtitle = htmlspecialchars(
            $subtitle,
            ENT_QUOTES,
            'UTF-8'
        );

        echo '<a href="' . $safeHref . '" style="' . $style['wrapper'] . '">';
        echo '<span style="' . $style['panel'] . '">';
        echo '<img'
            . ' src="' . $safeImage . '"'
            . ' alt="Jabatan Teknologi Digital dan Inovasi Negeri Sabah"'
            . ' style="' . $style['image'] . '"'
            . ' loading="eager"'
            . '>';
        echo '<span style="' . $style['subtitle'] . '">'
            . $safeSubtitle
            . '</span>';
        echo '</span>';
        echo '</a>';
    }
}

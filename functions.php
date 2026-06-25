<?php
/**
 * Ekinese – Theme-Funktionen.
 *
 * In einem Block-Theme ist functions.php optional. Wir nutzen sie nur für
 * Dinge, die sich nicht über theme.json / Templates abbilden lassen:
 * Theme-Supports, eigene Pattern-Kategorie, Block-Styles und Asset-Loading.
 *
 * @package Ekinese
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EKINESE_VERSION', wp_get_theme()->get( 'Version' ) );

require_once get_theme_file_path( 'inc/setup.php' );
require_once get_theme_file_path( 'inc/patterns.php' );
require_once get_theme_file_path( 'inc/block-styles.php' );
require_once get_theme_file_path( 'inc/taxonomies.php' );
require_once get_theme_file_path( 'inc/offices.php' );
require_once get_theme_file_path( 'inc/scheduling.php' );
require_once get_theme_file_path( 'inc/charity.php' );
require_once get_theme_file_path( 'inc/chat.php' );
require_once get_theme_file_path( 'inc/blueprint-sections.php' );
require_once get_theme_file_path( 'inc/products.php' );
require_once get_theme_file_path( 'inc/verkopen.php' );
require_once get_theme_file_path( 'inc/forms.php' );
require_once get_theme_file_path( 'inc/seo.php' );
require_once get_theme_file_path( 'inc/diamond.php' );
require_once get_theme_file_path( 'inc/newsletter.php' );
require_once get_theme_file_path( 'inc/lexicon.php' );
require_once get_theme_file_path( 'inc/integrations.php' );
require_once get_theme_file_path( 'inc/watches.php' );
require_once get_theme_file_path( 'inc/gemstones.php' );
require_once get_theme_file_path( 'inc/tickets.php' );
require_once get_theme_file_path( 'inc/notifications.php' );
require_once get_theme_file_path( 'inc/pickup.php' );
require_once get_theme_file_path( 'inc/stats.php' );
require_once get_theme_file_path( 'inc/account.php' );
require_once get_theme_file_path( 'inc/notify.php' );
require_once get_theme_file_path( 'inc/questions.php' );
require_once get_theme_file_path( 'inc/heatmap.php' );
require_once get_theme_file_path( 'inc/loyalty.php' );
require_once get_theme_file_path( 'inc/auctions.php' );
require_once get_theme_file_path( 'inc/i18n.php' );
require_once get_theme_file_path( 'inc/seo-index.php' );
require_once get_theme_file_path( 'inc/price-chart.php' );
require_once get_theme_file_path( 'inc/ratios.php' );
require_once get_theme_file_path( 'inc/price-tabs.php' );
require_once get_theme_file_path( 'inc/market.php' );
require_once get_theme_file_path( 'inc/trust.php' );
require_once get_theme_file_path( 'inc/rewards.php' );
require_once get_theme_file_path( 'inc/lottery.php' );
require_once get_theme_file_path( 'inc/partners.php' );
require_once get_theme_file_path( 'inc/inventory.php' );
require_once get_theme_file_path( 'inc/hr.php' );
require_once get_theme_file_path( 'inc/accounting.php' );
require_once get_theme_file_path( 'inc/portfolio.php' );
require_once get_theme_file_path( 'inc/marketplace.php' );
require_once get_theme_file_path( 'inc/news.php' );
require_once get_theme_file_path( 'inc/mail-flows.php' );
require_once get_theme_file_path( 'inc/social.php' );
require_once get_theme_file_path( 'inc/ads.php' );
require_once get_theme_file_path( 'inc/kyc.php' );
require_once get_theme_file_path( 'inc/driver.php' );
require_once get_theme_file_path( 'inc/installer.php' );
require_once get_theme_file_path( 'inc/social-login.php' );
require_once get_theme_file_path( 'inc/admin-menu.php' );
require_once get_theme_file_path( 'inc/live-ticker.php' );
require_once get_theme_file_path( 'inc/floating-contact.php' );
require_once get_theme_file_path( 'inc/sticky-cta.php' );
require_once get_theme_file_path( 'inc/calc-save.php' );
require_once get_theme_file_path( 'inc/compare.php' );
require_once get_theme_file_path( 'inc/year-review.php' );
require_once get_theme_file_path( 'inc/savings-goal.php' );
require_once get_theme_file_path( 'inc/city-pages.php' );
require_once get_theme_file_path( 'inc/kennisbank.php' );
require_once get_theme_file_path( 'inc/llms.php' );
require_once get_theme_file_path( 'inc/faq.php' );
require_once get_theme_file_path( 'inc/price-forecast.php' );
require_once get_theme_file_path( 'inc/hallmark.php' );
require_once get_theme_file_path( 'inc/ai-assistant.php' );
require_once get_theme_file_path( 'inc/photo-appraisal.php' );
require_once get_theme_file_path( 'inc/booking-slots.php' );
require_once get_theme_file_path( 'inc/appraisal-cert.php' );
require_once get_theme_file_path( 'inc/business-portal.php' );

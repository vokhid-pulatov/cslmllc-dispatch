<?php
if (!defined('ABSPATH')) { exit; }
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="CSLM LLC dispatch services for owner-operators and small motor carriers.">
    <?php wp_head(); ?>
</head>
<body <?php body_class('cslm-dispatch-portal'); ?>>
<?php wp_body_open(); ?>
<?php echo do_shortcode('[cslm_dispatch_portal]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php wp_footer(); ?>
</body>
</html>

<?php
/**
 * Title: Blaupause – Kategorie-Layout
 * Slug: ekinese/blueprint-category
 * Categories: ekinese, featured
 * Description: Das zentrale, wiederverwendbare Layout für Kategorie- und Inhaltsseiten. Einmal hier pflegen, überall einsetzbar.
 * Keywords: blaupause, kategorie, layout, blueprint
 * Block Types: core/post-content
 * Viewport Width: 1280
 *
 * @package Ekinese
 */
?>
<!-- wp:group {"className":"blueprint-category","layout":{"type":"constrained"}} -->
<div class="wp-block-group blueprint-category">

	<!--
	  ====================================================================
	  HIER DEIN FERTIGES KATEGORIE-LAYOUT EINFÜGEN
	  ====================================================================
	  Ersetze den Beispiel-Inhalt unten durch deinen Block-Markup.
	  So bekommst du den Markup:
	    1. Site-Editor / Block-Editor öffnen
	    2. Dein Layout markieren → Optionen (⋮) → "Als HTML kopieren"
	    3. Den kopierten Markup hier einfügen
	  Alternativ baust du das Layout im Editor und exportierst es als Pattern.

	  Strings, die du dynamisch machen willst (z. B. Kategorie-Name), per
	  Block-Bindings oder den Query-/Post-Blöcken einsetzen statt fest zu schreiben.
	  ====================================================================
	-->

	<!-- wp:columns {"align":"wide"} -->
	<div class="wp-block-columns alignwide">
		<!-- wp:column {"width":"66.66%"} -->
		<div class="wp-block-column" style="flex-basis:66.66%">
			<!-- wp:query {"queryId":0,"query":{"perPage":9,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":true}} -->
			<div class="wp-block-query">
				<!-- wp:post-template {"layout":{"type":"grid","columnCount":2}} -->
					<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /-->
					<!-- wp:post-title {"isLink":true,"fontSize":"large"} /-->
					<!-- wp:post-excerpt {"excerptLength":18} /-->
				<!-- /wp:post-template -->

				<!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"center"}} -->
					<!-- wp:query-pagination-previous /-->
					<!-- wp:query-pagination-numbers /-->
					<!-- wp:query-pagination-next /-->
				<!-- /wp:query-pagination -->
			</div>
			<!-- /wp:query -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"33.33%"} -->
		<div class="wp-block-column" style="flex-basis:33.33%">
			<!-- wp:group {"className":"sidebar-widget","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","right":"var:preset|spacing|40","bottom":"var:preset|spacing|40","left":"var:preset|spacing|40"}}},"backgroundColor":"surface","layout":{"type":"constrained"}} -->
			<div class="wp-block-group sidebar-widget has-surface-background-color has-background" style="padding:var(--wp--preset--spacing--40)">
				<!-- wp:heading {"level":3} -->
				<h3 class="wp-block-heading">Weitere Kategorien</h3>
				<!-- /wp:heading -->
				<!-- wp:categories {"showHierarchy":true} /-->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->

</div>
<!-- /wp:group -->

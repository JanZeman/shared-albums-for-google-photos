					<h3><?php esc_html_e( 'Required', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<table class="jzsa-settings-table jzsa-settings-table--params">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Parameter', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Description', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Default', 'janzeman-shared-albums-for-google-photos' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>link</code></td>
								<td><?php esc_html_e( 'Google Photos share URL (supports both full and short link formats)', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td><em><?php esc_html_e( 'Required', 'janzeman-shared-albums-for-google-photos' ); ?></em></td>
							</tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Core Parameters', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<table class="jzsa-settings-table jzsa-settings-table--params">
						<thead>
							<tr>
								<th>Parameter</th>
								<th>Description</th>
								<th>Default</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>mode</code></td>
								<td>Gallery mode:<br>
									• "gallery": Thumbnail gallery with optional paging or scrolling via <code>gallery-rows</code> and <code>gallery-scrollable</code>; each thumbnail includes a fullscreen button by default<br>
									• "slider": Single photo viewer with zoom support (pinch on touch devices)<br>
									• "carousel": Multiple photos visible at once (2 on mobile/tablet, 3 on desktop). Each photo includes a fullscreen button by default</td>
								<td>gallery</td>
							</tr>
							<tr>
								<td><code>limit</code></td>
								<td>Maximum number of album entries to display (photos and videos that remain after filters such as show-videos) from the album (1-300). Google Photos typically returns up to 300 entries per album. Note: infinite looping (swiping from the last photo back to the first) requires at least 4 entries.</td>
								<td>300</td>
							</tr>
							<tr>
								<td><code>source-width</code></td>
								<td>Photo width to fetch from Google for inline mode. Directly affects photo quality: higher values load sharper images.</td>
								<td>800</td>
							</tr>
							<tr>
								<td><code>source-height</code></td>
								<td>Photo height to fetch from Google for inline mode. Directly affects photo quality: higher values load sharper images.</td>
								<td>600</td>
							</tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Gallery Mode Options (those apply only for the default "gallery" mode)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<table class="jzsa-settings-table jzsa-settings-table--params">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Parameter', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Description', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Default', 'janzeman-shared-albums-for-google-photos' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>gallery-layout</code></td>
								<td><?php esc_html_e( 'Gallery layout algorithm: "grid" uses fixed columns with equal-size cells; "justified" ignores gallery-columns and packs photos into full-width rows using gallery-row-height, gallery-gap, container width, and each photo\'s aspect ratio, similar to Google Photos.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>grid</td>
							</tr>
							<tr>
								<td><code>gallery-sizing</code></td>
								<td><?php esc_html_e( 'Grid layout only. "ratio" keeps a fixed tile ratio (default), while "fill" stretches row heights to fill an explicit control height when width/height and gallery-rows are used. Ignored when gallery-layout="justified".', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>ratio</td>
							</tr>
							<tr>
								<td><code>gallery-columns</code></td>
								<td><?php esc_html_e( 'Number of columns on desktop. Applies only when gallery-layout="grid"; ignored when gallery-layout="justified".', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>3</td>
							</tr>
							<tr>
								<td><code>gallery-columns-tablet</code></td>
								<td><?php esc_html_e( 'Number of columns on tablet screens <= 768 px. Applies only when gallery-layout="grid"; ignored when gallery-layout="justified".', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>2</td>
							</tr>
							<tr>
								<td><code>gallery-columns-mobile</code></td>
								<td><?php esc_html_e( 'Number of columns on mobile screens <= 480 px. Applies only when gallery-layout="grid"; ignored when gallery-layout="justified".', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>1</td>
							</tr>
							<tr>
								<td><code>gallery-row-height</code></td>
								<td><?php esc_html_e( 'Target row height in pixels for gallery-layout="justified" (50-800). This controls justified density instead of gallery-columns.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>200</td>
							</tr>
							<tr>
								<td><code>gallery-rows</code></td>
								<td><?php esc_html_e( 'Number of visible gallery rows when row limiting is enabled. If more rows are available, gallery uses paging by default or scrolling when gallery-scrollable="true". Use 0 to show all rows.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>0 (all rows)</td>
							</tr>
							<tr>
								<td><code>gallery-scrollable</code></td>
								<td><?php esc_html_e( 'When set to "true" (and gallery-rows > 0), uses a single vertically scrollable gallery instead of page controls.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>false</td>
							</tr>
							<tr>
								<td><code>gallery-buttons-on-mobile</code></td>
								<td><?php esc_html_e( 'Controls when the action buttons (fullscreen, link, download) are visible on touch devices. Desktop always uses hover. "on-interaction" (default): interacting with a thumbnail activates that item, keeps its buttons visible until another item becomes active or the item leaves the viewport, and restores the same item after returning from fullscreen. "always": buttons are permanently visible on touch devices.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>on-interaction</td>
							</tr>
							<tr>
								<td><code>gallery-gap</code></td>
								<td><?php esc_html_e( 'Spacing between gallery thumbnails in pixels. Applies to both grid and justified layouts.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>4</td>
							</tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Appearance', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<table class="jzsa-settings-table jzsa-settings-table--params">
						<thead>
							<tr>
								<th>Parameter</th>
								<th>Description</th>
								<th>Default</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>background-color</code></td>
								<td>Background color hex code or "transparent"</td>
								<td>transparent</td>
							</tr>
							<tr>
								<td><code>controls-color</code></td>
									<td>Color for custom album controls (previous/next, fullscreen, link, download, play/pause) in inline mode. Any valid 6-digit hex color. Use <code>viewer-controls-color</code> first.</td>
								<td>#ffffff</td>
							</tr>
							<tr>
								<td><code>corner-radius</code></td>
								<td><?php esc_html_e( 'Rounded corner radius in pixels. Applies to slider, carousel, gallery thumbnails, and mosaic strips. Use 0 for square corners. Disabled in fullscreen mode. Use mosaic-corner-radius to override the radius for the mosaic strip independently.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>0</td>
							</tr>
							<tr>
								<td><code>image-fit</code></td>
								<td>How photos fit the frame: "cover" (fill and crop edges) or "contain" (show whole image, no cropping, may letterbox).</td>
								<td>cover</td>
							</tr>
							<tr>
								<td><code>width</code></td>
								<td>Width in pixels or "auto". In <code>mode="gallery"</code>, prefer <code>gallery-columns</code>/<code>gallery-rows</code>.</td>
								<td>400</td>
							</tr>
							<tr>
								<td><code>height</code></td>
								<td>Height in pixels or "auto". In <code>mode="gallery"</code>, prefer <code>gallery-columns</code>/<code>gallery-rows</code>.</td>
								<td>300</td>
							</tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Slideshow Settings', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<table class="jzsa-settings-table jzsa-settings-table--params">
						<thead>
							<tr>
								<th>Parameter</th>
								<th>Description</th>
								<th>Default</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>slideshow</code></td>
								<td>Slideshow mode: "auto" - slides advance automatically and the play/pause button is shown. "manual" - the play/pause button is shown but slides do not advance until the user presses play. "disabled" - no slideshow, no button. In <code>mode="gallery"</code> with pagination, this advances gallery pages automatically.</td>
								<td>disabled</td>
							</tr>
							<tr>
								<td><code>slideshow-delay</code></td>
								<td>Slideshow delay in normal mode, in seconds. Supports single values like "5" or ranges like "4-12". In paginated gallery mode this is the delay between page changes.</td>
								<td>5</td>
							</tr>
							<tr>
								<td><code>slideshow-autoresume</code></td>
									<td>When a user swipes or clicks to navigate forward or backward manually, the slideshow is interrupted. This is the number of seconds of inactivity after which the interrupted slideshow resumes and advances automatically. Set to "disabled" to turn off autoresume - the slideshow stays interrupted until the user presses play. Does not apply when the user pauses the slideshow via the pause button - that stays paused until manually resumed. Use <code>viewer-slideshow-autoresume</code> first.</td>
								<td>30</td>
							</tr>
							<tr>
								<td><code>start-at</code></td>
								<td>Starting photo: a 1-based photo index like "1" or "12", or "random" for a random starting point. Values out of range fall back to 1.</td>
								<td>1</td>
							</tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Display Options', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<table class="jzsa-settings-table jzsa-settings-table--params">
						<thead>
							<tr>
								<th>Parameter</th>
								<th>Description</th>
								<th>Default</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>show-navigation</code></td>
								<td>Show previous/next navigation arrows: "true" or "false"</td>
								<td>true</td>
							</tr>
							<tr>
								<td><code>show-link-button</code></td>
								<td>Show external link button in inline (non-fullscreen) view: "false" or "true"</td>
								<td>false</td>
							</tr>
							<tr>
								<td><code>show-download-button</code></td>
								<td>Show download button in inline (non-fullscreen) view: "false" or "true"</td>
								<td>false</td>
							</tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Info Boxes', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<p><?php echo wp_kses_post( __( 'Per-photo metadata overlays. Each zone accepts a format string with placeholders like <code>{date}</code>. Defaults are mode-aware: slider and carousel use <code>info-bottom</code> as the current-item counter by default, gallery tiles stay clean by default, paginated galleries use <code>gallery-info-bottom</code> for the page counter, and gallery fullscreen still shows the current-item counter. Leave a zone empty to hide it. See the Photo Info Overlay section below for available placeholders and examples.', 'janzeman-shared-albums-for-google-photos' ) ); ?></p>
					<table class="jzsa-settings-table jzsa-settings-table--params">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Parameter', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Position', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Default', 'janzeman-shared-albums-for-google-photos' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr><td><code>info-bottom</code></td><td><?php esc_html_e( 'Bottom center info box. In slider and carousel, the default is "{item} / {items}". In gallery mode it appears on each tile only when you set it explicitly. Supports {item}, {items}, {album-title} placeholders and all per-photo placeholders.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><?php echo wp_kses_post( __( '<code>{item} / {items}</code> in slider/carousel; <em>empty (off)</em> on gallery tiles', 'janzeman-shared-albums-for-google-photos' ) ); ?></td></tr>
							<tr><td><code>info-top</code></td><td><?php esc_html_e( 'Top center (first line)', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'empty (off)', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>info-top-secondary</code></td><td><?php esc_html_e( 'Top center (second line, below info-top)', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'empty (off)', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>gallery-info-bottom</code></td><td><?php esc_html_e( 'Gallery mode only - text shown in the page navigation bar when paginated gallery rows are enabled. Supports {page}, {pages}, and {album-title}. Uses the same info typography settings as the other info boxes, including info-font-size, info-font-family, and info-font-color. The default is "{page} / {pages}" for paginated galleries. Set it empty to hide or replace it with your own format string.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><?php echo wp_kses_post( __( '<code>{page} / {pages}</code> in paginated galleries', 'janzeman-shared-albums-for-google-photos' ) ); ?></td></tr>
							<tr><td><code>info-font-size</code></td><td><?php esc_html_e( 'Font size for all info boxes, including info-bottom (pixels)', 'janzeman-shared-albums-for-google-photos' ); ?></td><td>12</td></tr>
							<tr><td><code>info-font-family</code></td><td><?php echo wp_kses_post( __( '<strong>Recommended: use a font family stack, not a single font.</strong> Applies to all info boxes, including info-bottom. Use normal CSS <code>font-family</code> syntax with comma-separated fallbacks, for example <code>system-ui, sans-serif</code>, <code>Georgia, serif</code>, or <code>ui-monospace, SFMono-Regular, Consolas, monospace</code>. The font must already exist on the visitor device or be loaded by the theme/site. The plugin does not load web fonts; the browser falls back to the next family in the stack.', 'janzeman-shared-albums-for-google-photos' ) ); ?></td><td><code>system-ui, sans-serif</code></td></tr>
							<tr><td><code>info-font-color</code></td><td><?php echo wp_kses_post( __( 'Text color for all info boxes, including info-bottom and gallery-info-bottom. Any valid 6-digit hex color such as <code>#FFFFFF</code> or <code>#9FE8FF</code>. <strong>If you set it, it overrides the info-box text color only.</strong> If you leave it empty, info boxes continue using <code>controls-color</code> for backward compatibility; if neither is set, they fall back to white.', 'janzeman-shared-albums-for-google-photos' ) ); ?></td><td><?php esc_html_e( 'inherits controls-color', 'janzeman-shared-albums-for-google-photos' ); ?></td></tr>
								<tr><td><code>info-halo-effect</code></td><td><?php echo wp_kses_post( __( 'Enable the dark readability halo behind overlay text globally. Applies to <code>info-top</code>, <code>info-top-secondary</code>, <code>info-bottom</code>, <code>gallery-info-bottom</code>, and the album title in inline and viewer modes unless a per-box override below changes it.', 'janzeman-shared-albums-for-google-photos' ) ); ?></td><td><em><?php esc_html_e( 'true', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>info-top-halo-effect</code></td><td><?php esc_html_e( 'Per-box halo override for info-top. Set to "true" or "false" to override info-halo-effect for the top line only.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits info-halo-effect', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>info-top-secondary-halo-effect</code></td><td><?php esc_html_e( 'Per-box halo override for info-top-secondary. Set to "true" or "false" to override info-halo-effect for the secondary top line only.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits info-halo-effect', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>info-bottom-halo-effect</code></td><td><?php esc_html_e( 'Per-box halo override for info-bottom. Also affects the main slider/carousel/fullscreen counter when that counter is rendered through the bottom pagination pill.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits info-halo-effect', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>gallery-info-bottom-halo-effect</code></td><td><?php esc_html_e( 'Per-box halo override for the paginated gallery page counter shown in the gallery navigation bar.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits info-halo-effect', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>album-title-halo-effect</code></td><td><?php esc_html_e( 'Per-box halo override for the album title pill.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits info-halo-effect', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>info-wrap</code></td><td><?php echo wp_kses_post( __( 'Allow info box text to wrap to multiple lines instead of being cut off with "...". Useful when displaying long values such as descriptions ({description}) or combined EXIF strings. Set to "true" to enable wrapping; by default text is kept to a single line.<br><br><strong style="color: #d32f2f;">⚠️ IMPORTANT LIMITATION: Google Photos limits photo descriptions to 100 characters. Longer descriptions are truncated by Google, not by this plugin. Use info-wrap="true" to allow wrapping of text, but be aware of this Google-imposed limit.</strong>', 'janzeman-shared-albums-for-google-photos' ) ); ?></td><td><em><?php esc_html_e( 'false (single line)', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>info-text-align</code></td><td><?php esc_html_e( 'Text alignment for all info boxes at once. Accepted values: "left", "center", "right". Use the per-box variants below to align each box independently.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'center', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>info-top-text-align</code></td><td><?php esc_html_e( 'Text alignment for info-top only. Overrides info-text-align for this box. Accepted values: "left", "center", "right".', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits info-text-align', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>info-top-secondary-text-align</code></td><td><?php esc_html_e( 'Text alignment for info-top-secondary only. Overrides info-text-align for this box. Accepted values: "left", "center", "right".', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits info-text-align', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>info-bottom-text-align</code></td><td><?php esc_html_e( 'Text alignment for info-bottom only. Overrides info-text-align for this box. Accepted values: "left", "center", "right".', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits info-text-align', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
						</tbody>
					</table>

						<h3><?php esc_html_e( 'Viewer Settings', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
							<p><?php echo wp_kses_post( __( '<strong>Viewer</strong> means either Lightbox or Fullscreen, the two ways a visitor can open a larger photo view from the inline album. Use <code>viewer</code> to choose the active mode and <code>viewer-toggle</code> to choose how it opens. Shared settings use <code>viewer-*</code> parameters; mode-specific overrides use <code>lightbox-*</code> or <code>fullscreen-*</code>. There is no sideways inheritance between modes. The default viewer is Lightbox with a button - no parameters needed for that combination.', 'janzeman-shared-albums-for-google-photos' ) ); ?></p>
					<table class="jzsa-settings-table jzsa-settings-table--params">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Parameter', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Description', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Default', 'janzeman-shared-albums-for-google-photos' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr><td><code>viewer</code></td><td><?php echo wp_kses_post( __( 'Selects which viewer mode is active. Accepted values: <code>lightbox</code>, <code>fullscreen</code>, <code>lightbox, fullscreen</code>, and <code>disabled</code>. When both modes are active, each shows a button and <code>viewer-toggle</code> is ignored.', 'janzeman-shared-albums-for-google-photos' ) ); ?></td><td><em>lightbox</em></td></tr>
							<tr><td><code>viewer-toggle</code></td><td><?php echo wp_kses_post( __( 'How the active viewer opens. Accepted values: <code>button</code> (dedicated button only), <code>click</code> (button and single click), <code>double-click</code> (button and double-click). Ignored when <code>viewer="lightbox, fullscreen"</code>.', 'janzeman-shared-albums-for-google-photos' ) ); ?></td><td><em>button</em></td></tr>
							<tr><td><code>viewer-max-width</code></td><td><?php esc_html_e( 'Maximum displayed width for either viewer mode, in pixels.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'not applied', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>viewer-max-height</code></td><td><?php esc_html_e( 'Maximum displayed height for either viewer mode, in pixels.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'not applied', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>viewer-source-width</code></td><td><?php esc_html_e( 'Photo width fetched from Google for either viewer mode.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td>1920</td></tr>
							<tr><td><code>viewer-source-height</code></td><td><?php esc_html_e( 'Photo height fetched from Google for either viewer mode.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td>1440</td></tr>
							<tr><td><code>viewer-image-fit</code></td><td><?php esc_html_e( 'Photo fitting for either viewer mode: "contain" or "cover".', 'janzeman-shared-albums-for-google-photos' ); ?></td><td>contain</td></tr>
							<tr><td><code>viewer-background-color</code></td><td><?php esc_html_e( 'Background or backdrop color for either viewer mode.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'mode default', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>viewer-corner-radius</code></td><td><?php esc_html_e( 'Corner radius of the displayed viewer frame, in pixels.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td>0</td></tr>
							<tr><td><code>viewer-controls-color</code></td><td><?php esc_html_e( 'Navigation and action control color for either viewer mode.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits controls-color', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>viewer-video-controls-color</code></td><td><?php esc_html_e( 'Video control color for either viewer mode.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits video-controls-color', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>viewer-video-controls-autohide</code></td><td><?php esc_html_e( 'Whether video controls auto-hide in either viewer mode.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits video-controls-autohide', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>viewer-show-navigation</code></td><td><?php esc_html_e( 'Whether previous and next controls are shown in either viewer mode.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits show-navigation', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>viewer-show-link-button</code></td><td><?php esc_html_e( 'Whether the Google Photos link button is shown in either viewer mode.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits show-link-button', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>viewer-show-download-button</code></td><td><?php esc_html_e( 'Whether the download button is shown in either viewer mode.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits show-download-button', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>viewer-slideshow</code></td><td><?php esc_html_e( 'Slideshow behavior for either viewer mode: "auto", "manual", or "disabled".', 'janzeman-shared-albums-for-google-photos' ); ?></td><td>disabled</td></tr>
							<tr><td><code>viewer-slideshow-delay</code></td><td><?php esc_html_e( 'Slideshow delay for either viewer mode, in seconds.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td>5</td></tr>
							<tr><td><code>viewer-slideshow-autoresume</code></td><td><?php esc_html_e( 'Inactivity delay before a viewer slideshow resumes, or "disabled".', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits slideshow-autoresume', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>viewer-info-bottom</code></td><td><?php esc_html_e( 'Bottom info format in either viewer mode.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits viewer-mode legacy value', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>viewer-info-top</code></td><td><?php esc_html_e( 'First top info format in either viewer mode.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits info-top', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>viewer-info-top-secondary</code></td><td><?php esc_html_e( 'Second top info format in either viewer mode.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits info-top-secondary', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>viewer-info-font-size</code></td><td><?php esc_html_e( 'Info font size in either viewer mode.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits info-font-size', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>viewer-info-font-family</code></td><td><?php esc_html_e( 'Info font family in either viewer mode.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits info-font-family', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>viewer-info-font-color</code></td><td><?php esc_html_e( 'Info text color in either viewer mode.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits info-font-color', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>viewer-mosaic</code></td><td><?php esc_html_e( 'Enable the thumbnail mosaic in either viewer mode.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td>false</td></tr>
							<tr><td><code>viewer-mosaic-position</code></td><td><?php esc_html_e( 'Viewer mosaic position: "top", "bottom", "left", or "right".', 'janzeman-shared-albums-for-google-photos' ); ?></td><td>bottom</td></tr>
							<tr><td><code>viewer-mosaic-layout</code></td><td><?php esc_html_e( 'Viewer mosaic layout: "outer" or "overlay".', 'janzeman-shared-albums-for-google-photos' ); ?></td><td>outer</td></tr>
							<tr><td><code>viewer-mosaic-count</code></td><td><?php esc_html_e( 'Viewer mosaic thumbnail count, or "auto".', 'janzeman-shared-albums-for-google-photos' ); ?></td><td>auto</td></tr>
							<tr><td><code>viewer-mosaic-gap</code></td><td><?php esc_html_e( 'Gap between viewer mosaic thumbnails, in pixels.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td>8</td></tr>
							<tr><td><code>viewer-mosaic-opacity</code></td><td><?php esc_html_e( 'Opacity of inactive viewer mosaic thumbnails, from 0 to 1.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td>0.3</td></tr>
							<tr><td><code>viewer-mosaic-background</code></td><td><?php esc_html_e( 'Background color of the viewer mosaic strip.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'transparent', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
							<tr><td><code>viewer-mosaic-corner-radius</code></td><td><?php esc_html_e( 'Corner radius of viewer mosaic thumbnails, in pixels.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><em><?php esc_html_e( 'inherits mosaic corner radius', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
						</tbody>
						</table>

						<h3><?php esc_html_e( 'Mode-Specific Overrides', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
						<p><?php echo wp_kses_post( __( 'As explained above, use the shared <code>viewer-*</code> parameters first and switch to <code>lightbox-*</code> or <code>fullscreen-*</code> only when one viewer mode needs a different value.', 'janzeman-shared-albums-for-google-photos' ) ); ?></p>
							<table class="jzsa-settings-table jzsa-settings-table--params">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Parameter', 'janzeman-shared-albums-for-google-photos' ); ?></th>
										<th><?php esc_html_e( 'Description', 'janzeman-shared-albums-for-google-photos' ); ?></th>
										<th><?php esc_html_e( 'Default', 'janzeman-shared-albums-for-google-photos' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td><code>lightbox-fullscreen</code></td>
											<td><?php echo wp_kses_post( __( 'Controls whether the Fullscreen button appears inside the Lightbox view, enabling the three-step path Inline &rarr; Lightbox &rarr; Fullscreen. Accepted values: <code>button</code> and <code>disabled</code>. Default is <code>disabled</code> - except when both modes are active (e.g. <code>viewer="lightbox, fullscreen"</code>), in which case the default becomes <code>button</code> automatically. Set an explicit value to override this in either direction. Unique to Lightbox; no Fullscreen counterpart.', 'janzeman-shared-albums-for-google-photos' ) ); ?></td>
										<td>disabled *</td>
									</tr>
									<tr>
										<td><code>lightbox-backdrop-color</code></td>
											<td><?php echo wp_kses_post( __( 'Color of the dimmed overlay behind the lightbox box. Accepts a hex code, <code>transparent</code>, or an <code>rgba()</code>/<code>hsla()</code> value. Semi-transparent values are typical because the page can show through. Unique to Lightbox; no Fullscreen counterpart.', 'janzeman-shared-albums-for-google-photos' ) ); ?></td>
										<td>rgba(0,0,0,0.85)</td>
									</tr>
							</tbody>
						</table>

					<h3><?php esc_html_e( 'Mosaic Thumbnail Strip', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<p><?php esc_html_e( 'Display a strip of thumbnail previews alongside the main slider or carousel. Works with mode="slider" and mode="carousel". The strip is synchronized with the main swiper - clicking a thumbnail jumps to that photo.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
					<table class="jzsa-settings-table jzsa-settings-table--params">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Parameter', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Description', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Default', 'janzeman-shared-albums-for-google-photos' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>mosaic</code></td>
								<td><?php esc_html_e( 'Enable the mosaic thumbnail strip: "true" or "false".', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>false</td>
							</tr>
							<tr>
								<td><code>mosaic-position</code></td>
								<td><?php esc_html_e( 'Position of the thumbnail strip relative to the main viewer: "top", "bottom", "left", or "right".', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>bottom</td>
							</tr>
							<tr>
								<td><code>mosaic-count</code></td>
								<td><?php esc_html_e( 'Number of thumbnails visible at once in the strip. Use an integer (e.g. "5") or "auto" to let the plugin calculate the best fit based on the available space.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>auto</td>
							</tr>
							<tr>
								<td><code>mosaic-gap</code></td>
								<td><?php esc_html_e( 'Gap in pixels between thumbnails in the mosaic strip.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>8</td>
							</tr>
							<tr>
								<td><code>mosaic-opacity</code></td>
								<td><?php esc_html_e( 'Opacity of inactive (non-active) thumbnails in the mosaic strip. Accepts a value between 0 (invisible) and 1 (fully opaque). The active thumbnail is always fully opaque.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>0.3</td>
							</tr>
							<tr>
								<td><code>mosaic-background</code></td>
								<td><?php esc_html_e( 'Background color of the inline mosaic strip container. Accepts "transparent" or a 6-digit hex color (e.g. "#000000"). Useful to keep thumbnails visible on bright images.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>transparent</td>
							</tr>
							<tr>
								<td><code>mosaic-corner-radius</code></td>
								<td><?php esc_html_e( 'Rounded corner radius in pixels for the mosaic strip and its thumbnails. When not set, inherits from corner-radius.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td><?php esc_html_e( 'corner-radius', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							</tr>
							</tbody>
						</table>

						<h3><?php esc_html_e( 'Video Support (Experimental)', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<p><?php echo wp_kses( __( 'Albums containing videos will attempt to detect and play them using the built-in HTML5 video element with Plyr-based controls. Please notice: <strong>This is an experimental feature. The video playback experience might not be perfect under all conditions.</strong>', 'janzeman-shared-albums-for-google-photos' ), array( 'strong' => array() ) ); ?></p>
					<table class="jzsa-settings-table jzsa-settings-table--params">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Parameter', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Description', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Default', 'janzeman-shared-albums-for-google-photos' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>show-videos</code></td>
								<td><?php esc_html_e( 'Include videos from mixed albums: "true" or "false". Set to "false" to display only photos and filter out all video items.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>false</td>
							</tr>
							<tr>
								<td><code>video-controls-autohide</code></td>
									<td><?php esc_html_e( 'Auto-hide the video control bar after a few seconds of inactivity in inline mode: "true" or "false". When enabled, controls reappear on hover or tap. Use viewer-video-controls-autohide for Lightbox and Fullscreen, then use mode-specific overrides only when one viewer mode needs different behavior.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>false</td>
							</tr>
							<tr>
								<td><code>video-controls-color</code></td>
									<td><?php esc_html_e( 'Accent color for video play button and control bar in inline mode. Any valid CSS hex color (e.g. "#00b2ff", "#FF69B4"). Use viewer-video-controls-color for Lightbox and Fullscreen, then use mode-specific overrides only when one viewer mode needs a different color.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>#00b2ff</td>
							</tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Other Settings', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<table class="jzsa-settings-table jzsa-settings-table--params">
						<thead>
							<tr>
								<th>Parameter</th>
								<th>Description</th>
								<th>Default</th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>album-cache-refresh</code></td>
								<td>How often the album's photo list is re-fetched from Google Photos, in hours. Only the album listing is affected; per-photo metadata (EXIF, description, etc.) is cached separately and is not cleared by this setting. Useful for albums that are updated frequently (e.g. live event albums). Example: album-cache-refresh="1" to refresh every hour. The old name <code>cache-refresh</code> still works for backward compatibility.</td>
								<td>168 (7 days)</td>
							</tr>
							<tr>
								<td><code>interaction-lock</code></td>
								<td>Master interaction lock: when "true", disables swipe/drag, keyboard navigation, click/tap photo navigation, gallery thumbnail fullscreen opening, and fullscreen entry gestures/buttons. Interactive controls are hidden; passive indicators like counter/progress can remain visible.</td>
								<td>false</td>
							</tr>
							<tr>
								<td><code>download-size-warning</code></td>
								<td>Large-download warning threshold (in MB) for proxied downloads (photo or video). If exceeded, the visitor gets a yes/no confirmation dialog before continuing. Set <code>0</code> to disable the warning. Hard server limit: 512 MB.</td>
								<td>128</td>
							</tr>
						</tbody>
					</table>

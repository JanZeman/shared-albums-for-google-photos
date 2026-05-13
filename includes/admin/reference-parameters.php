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
								<td>Photo width to fetch from Google for inline mode</td>
								<td>800</td>
							</tr>
							<tr>
								<td><code>source-height</code></td>
								<td>Photo height to fetch from Google for inline mode</td>
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
								<td><?php esc_html_e( 'Gallery layout algorithm: "grid" (equal-size cells) or "justified" (photos fill each row at their natural aspect ratio, like Google Photos)', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>grid</td>
							</tr>
							<tr>
								<td><code>gallery-sizing</code></td>
								<td><?php esc_html_e( 'Grid gallery sizing: "ratio" keeps a fixed tile ratio (default), while "fill" stretches row heights to fill an explicit control height when width/height and gallery-rows are used.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>ratio</td>
							</tr>
							<tr>
								<td><code>gallery-columns</code></td>
								<td><?php esc_html_e( 'Number of columns on desktop (grid layout only)', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>3</td>
							</tr>
							<tr>
								<td><code>gallery-columns-tablet</code></td>
								<td><?php esc_html_e( 'Number of columns on tablet screens ≤ 768 px (grid layout only)', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>2</td>
							</tr>
							<tr>
								<td><code>gallery-columns-mobile</code></td>
								<td><?php esc_html_e( 'Number of columns on mobile screens ≤ 480 px (grid layout only)', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>1</td>
							</tr>
							<tr>
								<td><code>gallery-row-height</code></td>
								<td><?php esc_html_e( 'Target row height in pixels for the justified layout (50-800)', 'janzeman-shared-albums-for-google-photos' ); ?></td>
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
								<td>Color for custom album controls (previous/next, fullscreen, link, download, play/pause) in inline mode. Any valid 6-digit hex color. Use <code>fullscreen-controls-color</code> to override this in fullscreen.</td>
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
								<td>When a user swipes or clicks to navigate forward or backward manually, the slideshow is interrupted. This is the number of seconds of inactivity after which the interrupted slideshow resumes and advances automatically. Set to "disabled" to turn off autoresume - the slideshow stays interrupted until the user presses play. Does not apply when the user pauses the slideshow via the pause button - that stays paused until manually resumed. This sets inline behavior; use <code>fullscreen-slideshow-autoresume</code> to override in fullscreen.</td>
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
							<tr><td><code>info-halo-effect</code></td><td><?php echo wp_kses_post( __( 'Enable the dark readability halo behind overlay text globally. Applies to <code>info-top</code>, <code>info-top-secondary</code>, <code>info-bottom</code>, <code>gallery-info-bottom</code>, and the album title in both inline and fullscreen views unless a per-box override below changes it.', 'janzeman-shared-albums-for-google-photos' ) ); ?></td><td><em><?php esc_html_e( 'true', 'janzeman-shared-albums-for-google-photos' ); ?></em></td></tr>
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

					<h3><?php esc_html_e( 'Fullscreen Settings', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
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
								<td><code>fullscreen-source-width</code></td>
								<td>Photo width to fetch from Google for fullscreen mode</td>
								<td>1920</td>
							</tr>
							<tr>
								<td><code>fullscreen-source-height</code></td>
								<td>Photo height to fetch from Google for fullscreen mode</td>
								<td>1440</td>
							</tr>
							<tr>
								<td><code>fullscreen-display-max-width</code></td>
								<td>Maximum displayed photo width in fullscreen, in pixels. Keeps the photo centered and preserves aspect ratio inside the capped box. Does not change the fetched source image; use <code>fullscreen-source-width</code> for that.</td>
								<td><em>not applied</em></td>
							</tr>
							<tr>
								<td><code>fullscreen-display-max-height</code></td>
								<td>Maximum displayed photo height in fullscreen, in pixels. Keeps the photo centered and preserves aspect ratio inside the capped box. Does not change the fetched source image; use <code>fullscreen-source-height</code> for that.</td>
								<td><em>not applied</em></td>
							</tr>
							<tr>
								<td><code>fullscreen-slideshow</code></td>
								<td>Slideshow mode in fullscreen: "auto", "manual", or "disabled". Same behavior as <code>slideshow</code> but applies only when in fullscreen.</td>
								<td>disabled</td>
							</tr>
							<tr>
								<td><code>fullscreen-slideshow-delay</code></td>
								<td>Slideshow delay in fullscreen mode, in seconds, supports ranges like "3-5" or single values</td>
								<td>5</td>
							</tr>
							<tr>
								<td><code>fullscreen-toggle</code></td>
								<td><?php echo wp_kses_post( __( 'How fullscreen is toggled: "button-only" (default) requires the fullscreen button, "click" enters fullscreen on a single click, "double-click" toggles fullscreen on double-click, or "disabled" to prevent fullscreen entirely. Note: "click" disables single-click navigation in fullscreen mode, so mouse users lose the ability to click left/right to browse. <strong>"double-click" is recommended</strong> - it keeps single-click navigation in fullscreen while still offering a gesture shortcut to enter and exit. Both <code>lightbox-toggle</code> and <code>fullscreen-toggle</code> can be active at the same time — the visitor sees both buttons and chooses which expanded view to open. Set <code>fullscreen-toggle="disabled"</code> to hide the fullscreen button when you want lightbox-only behaviour (see Lightbox Settings below).', 'janzeman-shared-albums-for-google-photos' ) ); ?></td>
								<td>button-only</td>
							</tr>
							<tr>
								<td><code>fullscreen-image-fit</code></td>
								<td>How photos fit the frame in fullscreen: "contain" (default, show whole image, no cropping) or "cover" (fill and crop edges).</td>
								<td>contain</td>
							</tr>
							<tr>
								<td><code>fullscreen-background-color</code></td>
								<td><?php esc_html_e( 'Background color for fullscreen mode. Overrides background-color when viewing in fullscreen. Hex code or "transparent".', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>#000000</td>
							</tr>
							<tr>
								<td><code>fullscreen-show-navigation</code></td>
								<td>Show previous/next navigation arrows in fullscreen: "true" or "false". Defaults to <code>show-navigation</code> when omitted.</td>
								<td>inherits show-navigation</td>
							</tr>
							<tr>
								<td><code>fullscreen-controls-color</code></td>
								<td>Color for custom album controls in fullscreen view. Any valid 6-digit hex color. Defaults to <code>controls-color</code> when omitted.</td>
								<td>inherits controls-color</td>
							</tr>
							<tr>
								<td><code>fullscreen-video-controls-color</code></td>
								<td>Accent color for video play button and control bar in fullscreen. Defaults to <code>video-controls-color</code> when omitted.</td>
								<td>inherits video-controls-color</td>
							</tr>
							<tr>
								<td><code>fullscreen-video-controls-autohide</code></td>
								<td>Auto-hide video control bar in fullscreen after inactivity: "true" or "false". Defaults to <code>video-controls-autohide</code> when omitted.</td>
								<td>inherits video-controls-autohide</td>
							</tr>
							<tr>
								<td><code>fullscreen-slideshow-autoresume</code></td>
								<td>Number of inactivity seconds before fullscreen slideshow autoresumes, or "disabled". Defaults to <code>slideshow-autoresume</code> when omitted.</td>
								<td>inherits slideshow-autoresume</td>
							</tr>
							<tr>
								<td><code>fullscreen-show-link-button</code></td>
								<td>Show external link button in fullscreen view: "false" or "true". Defaults to <code>show-link-button</code> when omitted.</td>
								<td>inherits show-link-button</td>
							</tr>
							<tr>
								<td><code>fullscreen-show-download-button</code></td>
								<td>Show download button in fullscreen view: "false" or "true". Defaults to <code>show-download-button</code> when omitted.</td>
								<td>inherits show-download-button</td>
							</tr>
							<tr><td><code>fullscreen-info-bottom</code></td><td><?php esc_html_e( 'Bottom center info box in fullscreen. In gallery mode it defaults to "{item} / {items}" even though gallery tiles are clean by default. In slider and carousel it inherits from info-bottom when omitted.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><?php echo wp_kses_post( __( 'gallery: <code>{item} / {items}</code>; slider/carousel: inherits <code>info-bottom</code>', 'janzeman-shared-albums-for-google-photos' ) ); ?></td></tr>
							<tr><td><code>fullscreen-info-top</code></td><td><?php esc_html_e( 'Info box: top center first line in fullscreen. Inherits from info-top when omitted.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><?php esc_html_e( 'inherits info-top', 'janzeman-shared-albums-for-google-photos' ); ?></td></tr>
							<tr><td><code>fullscreen-info-top-secondary</code></td><td><?php esc_html_e( 'Info box: top center second line in fullscreen. Inherits from info-top-secondary when omitted.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><?php esc_html_e( 'inherits info-top-secondary', 'janzeman-shared-albums-for-google-photos' ); ?></td></tr>
							<tr><td><code>fullscreen-info-font-size</code></td><td><?php esc_html_e( 'Font size for all info boxes in fullscreen, including fullscreen-info-bottom (pixels). Defaults to info-font-size when omitted.', 'janzeman-shared-albums-for-google-photos' ); ?></td><td><?php esc_html_e( 'inherits info-font-size', 'janzeman-shared-albums-for-google-photos' ); ?></td></tr>
							<tr><td><code>fullscreen-info-font-family</code></td><td><?php echo wp_kses_post( __( 'Fullscreen override for the info box font family stack, including fullscreen-info-bottom. Uses the same comma-separated CSS <code>font-family</code> syntax as <code>info-font-family</code> and defaults to <code>info-font-family</code> when omitted.', 'janzeman-shared-albums-for-google-photos' ) ); ?></td><td><?php esc_html_e( 'inherits info-font-family', 'janzeman-shared-albums-for-google-photos' ); ?></td></tr>
							<tr><td><code>fullscreen-info-font-color</code></td><td><?php echo wp_kses_post( __( 'Fullscreen override for the info box text color, including fullscreen-info-bottom. Uses the same 6-digit hex syntax as <code>info-font-color</code>. If omitted, it inherits <code>info-font-color</code>; if neither is set, fullscreen info text follows <code>fullscreen-controls-color</code>, then <code>controls-color</code>, then white.', 'janzeman-shared-albums-for-google-photos' ) ); ?></td><td><?php esc_html_e( 'inherits info-font-color', 'janzeman-shared-albums-for-google-photos' ); ?></td></tr>
						</tbody>
					</table>

					<h3><?php esc_html_e( 'Lightbox Settings', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<p><?php echo wp_kses_post( __( 'The lightbox is an alternative to native fullscreen. Instead of the browser taking over the whole screen, clicking a photo opens it in a dimmed overlay <strong>on top of the page</strong>, in a size-capped box. Enabling it (any <code>lightbox-toggle</code> value other than <code>disabled</code>) replaces native fullscreen for that album, and <code>interaction-lock="true"</code> disables it too. While the lightbox is open it reuses the album\'s <code>fullscreen-*</code> settings (controls, navigation, slideshow, info boxes); the parameters below are the lightbox-specific extras.', 'janzeman-shared-albums-for-google-photos' ) ); ?></p>
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
								<td><code>lightbox-toggle</code></td>
								<td><?php echo wp_kses_post( __( 'How the lightbox is opened: <code>disabled</code> (default, feature off - native fullscreen behaviour is unchanged), <code>button-only</code> (only the corner button opens it), <code>click</code> (a single click on the photo opens it), or <code>double-click</code> (a double-click opens and closes it). Any value other than <code>disabled</code> enables the lightbox. The fullscreen button remains visible alongside it by default — add <code>fullscreen-toggle="disabled"</code> if you want lightbox-only with no native fullscreen option. Note: like <code>fullscreen-toggle</code>, <code>click</code> uses clicks to close the lightbox, so click-to-navigate is unavailable in that mode - <code>double-click</code> is the friendlier choice when visitors also click to browse.', 'janzeman-shared-albums-for-google-photos' ) ); ?></td>
								<td>disabled</td>
							</tr>
							<tr>
								<td><code>lightbox-max-width</code></td>
								<td><?php echo wp_kses_post( __( 'Maximum width of the lightbox box, in pixels. The photo is centered and preserves its aspect ratio inside the capped box. This is the answer to "open it at a fixed size instead of full screen". Does not change the fetched image resolution.', 'janzeman-shared-albums-for-google-photos' ) ); ?></td>
								<td><em>viewport</em></td>
							</tr>
							<tr>
								<td><code>lightbox-max-height</code></td>
								<td><?php esc_html_e( 'Maximum height of the lightbox box, in pixels. Same semantics as lightbox-max-width.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td><em>viewport</em></td>
							</tr>
							<tr>
								<td><code>lightbox-image-fit</code></td>
								<td><?php esc_html_e( 'How photos fit the box in the lightbox: "contain" (default, show the whole image, no cropping) or "cover" (fill the box and crop the edges).', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>contain</td>
							</tr>
							<tr>
								<td><code>lightbox-background-color</code></td>
								<td><?php echo wp_kses_post( __( 'Colour of the dimmed backdrop behind the lightbox box. Accepts a hex code, <code>transparent</code>, or an <code>rgba()</code>/<code>hsla()</code> value (semi-transparent is typical so the page shows through).', 'janzeman-shared-albums-for-google-photos' ) ); ?></td>
								<td>rgba(0,0,0,0.92)</td>
							</tr>
							<tr>
								<td><code>lightbox-corner-radius</code></td>
								<td><?php esc_html_e( 'Corner radius of the floating lightbox box, in pixels.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>0</td>
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

					<h3><?php esc_html_e( 'Fullscreen Mosaic Thumbnail Strip', 'janzeman-shared-albums-for-google-photos' ); ?></h3>
					<p><?php esc_html_e( 'Display a thumbnail preview strip only while the viewer is in fullscreen. This can be enabled independently from the inline mosaic strip and has its own layout, count, spacing, opacity, and corner radius settings.', 'janzeman-shared-albums-for-google-photos' ); ?></p>
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
								<td><code>fullscreen-mosaic</code></td>
								<td><?php esc_html_e( 'Enable the fullscreen mosaic thumbnail strip: "true" or "false". This is independent from the inline mosaic strip.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>false</td>
							</tr>
							<tr>
								<td><code>fullscreen-mosaic-position</code></td>
								<td><?php esc_html_e( 'Position of the thumbnail strip in fullscreen: "top", "bottom", "left", or "right".', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>bottom</td>
							</tr>
							<tr>
								<td><code>fullscreen-mosaic-layout</code></td>
							<td><?php esc_html_e( 'Fullscreen strip layout: "outer" reserves separate space for the strip outside the photo (supported for "top" and "bottom" positions only), while "overlay" keeps the strip on top of the photo. "outer" is the default.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							<td>outer</td>
							</tr>
							<tr>
								<td><code>fullscreen-mosaic-count</code></td>
								<td><?php esc_html_e( 'Number of thumbnails visible at once in the fullscreen strip. Use an integer or "auto".', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>auto</td>
							</tr>
							<tr>
								<td><code>fullscreen-mosaic-gap</code></td>
								<td><?php esc_html_e( 'Gap in pixels between thumbnails in the fullscreen mosaic strip.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>8</td>
							</tr>
							<tr>
								<td><code>fullscreen-mosaic-opacity</code></td>
								<td><?php esc_html_e( 'Opacity of inactive thumbnails in the fullscreen mosaic strip. Accepts a value between 0 and 1.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>0.3</td>
							</tr>
							<tr>
								<td><code>fullscreen-mosaic-background</code></td>
								<td><?php esc_html_e( 'Background color of the fullscreen mosaic strip container. Accepts "transparent" or a 6-digit hex color (e.g. "#000000"). Useful in fullscreen to keep thumbnails visible regardless of image content behind the strip.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>transparent</td>
							</tr>
							<tr>
								<td><code>fullscreen-mosaic-corner-radius</code></td>
								<td><?php esc_html_e( 'Rounded corner radius in pixels for the fullscreen mosaic strip and its thumbnails. When not set, inherits from mosaic-corner-radius, then corner-radius.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td><?php esc_html_e( 'mosaic-corner-radius', 'janzeman-shared-albums-for-google-photos' ); ?></td>
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
								<td><?php esc_html_e( 'Auto-hide the video control bar after a few seconds of inactivity in inline mode: "true" or "false". When enabled, controls reappear on hover or tap. Use fullscreen-video-controls-autohide to override this in fullscreen.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>false</td>
							</tr>
							<tr>
								<td><code>video-controls-color</code></td>
								<td><?php esc_html_e( 'Accent color for video play button and control bar in inline mode. Any valid CSS hex color (e.g. "#00b2ff", "#FF69B4"). Use fullscreen-video-controls-color to override this in fullscreen.', 'janzeman-shared-albums-for-google-photos' ); ?></td>
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
								<td>How often the album's photo list is re-fetched from Google Photos, in hours. Only the album listing is affected — per-photo metadata (EXIF, description, etc.) is cached separately and is not cleared by this setting. Useful for albums that are updated frequently (e.g. live event albums). Example: album-cache-refresh="1" to refresh every hour. The old name <code>cache-refresh</code> still works for backward compatibility.</td>
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

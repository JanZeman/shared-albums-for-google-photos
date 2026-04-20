					<p><?php esc_html_e( 'Box content is a text string. You can use plain text (as in the preview above) or placeholders like {date}, {item}, and {page} that resolve to per-photo metadata or gallery page state. Placeholders that cannot be resolved (no data available) are silently removed together with any surrounding separator characters. Most placeholders resolve instantly; background metadata appears with a brief delay only the first time and then loads immediately from cache. Layout options such as info-wrap and the info text alignment parameters apply to any info box that renders these placeholders, including fullscreen overlays.', 'janzeman-shared-albums-for-google-photos' ); ?></p>

					<table class="jzsa-settings-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Placeholder', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'What it shows', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Example', 'janzeman-shared-albums-for-google-photos' ); ?></th>
								<th><?php esc_html_e( 'Availability', 'janzeman-shared-albums-for-google-photos' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><code>{item}</code></td>
								<td><?php esc_html_e( 'Current item number (e.g. "3", or "3-4" in carousel with multiple visible slides)', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>3</td>
								<td>✅ <?php esc_html_e( 'Available', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							</tr>
							<tr>
								<td><code>{items}</code></td>
								<td><?php esc_html_e( 'Total item count (e.g. "41")', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>41</td>
								<td>✅ <?php esc_html_e( 'Available', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							</tr>
							<tr>
								<td><code>{page}</code></td>
								<td><?php esc_html_e( 'Current gallery page number when paginated gallery rows are enabled', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>2</td>
								<td>✅ <?php esc_html_e( 'Available in gallery-info-bottom only', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							</tr>
							<tr>
								<td><code>{pages}</code></td>
								<td><?php esc_html_e( 'Total number of gallery pages when paginated gallery rows are enabled', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>9</td>
								<td>✅ <?php esc_html_e( 'Available in gallery-info-bottom only', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							</tr>
							<tr>
								<td><code>{album-title}</code></td>
								<td><?php esc_html_e( 'Album title', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>Trip to Vietnam</td>
								<td>✅ <?php esc_html_e( 'Available', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							</tr>
							<tr>
								<td><code>{date}</code></td>
								<td><?php esc_html_e( 'Photo date, formatted in the visitor\'s browser locale', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>Apr 3, 2024</td>
								<td>✅ <?php esc_html_e( 'Available', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							</tr>
							<tr>
								<td><code>{dimensions}</code></td>
								<td><?php esc_html_e( 'Photo resolution in pixels', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>6000×4000</td>
								<td>✅ <?php esc_html_e( 'Available', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							</tr>
							<tr>
								<td><code>{megapixels}</code></td>
								<td><?php esc_html_e( 'Resolution in megapixels (derived from dimensions)', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>24.2 Mpix</td>
								<td>✅ <?php esc_html_e( 'Available', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							</tr>
							<tr>
								<td><code>{filesize}</code></td>
								<td><?php esc_html_e( 'File size of the original photo', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>7.0 MB</td>
								<td>✅ <?php esc_html_e( 'Available', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							</tr>
							<tr>
								<td><code>{filename}</code></td>
								<td><?php esc_html_e( 'Original filename as stored in Google Photos', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>IMG_4095.JPG</td>
								<td>⏳ <?php esc_html_e( 'May appear with delay', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							</tr>
							<tr>
								<td><code>{name}</code></td>
								<td><?php esc_html_e( 'Filename without extension', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>IMG_4095</td>
								<td>⏳ <?php esc_html_e( 'May appear with delay', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							</tr>
							<tr>
								<td><code>{description}</code></td>
								<td><?php esc_html_e( 'Photo description/caption from Google Photos, when present', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>Tūī bird, New Zealand</td>
								<td>⏳ <?php esc_html_e( 'May appear with delay', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							</tr>
							<tr>
								<td><code>{camera}</code></td>
								<td><?php esc_html_e( 'Best-guess display value combining EXIF make/model', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>Canon EOS 5D</td>
								<td>⏳ <?php esc_html_e( 'May appear with delay', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							</tr>
							<tr>
								<td><code>{camera-make}</code></td>
								<td><?php esc_html_e( 'Raw EXIF camera make as provided by Google Photos', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>Canon</td>
								<td>⏳ <?php esc_html_e( 'May appear with delay', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							</tr>
							<tr>
								<td><code>{camera-model}</code></td>
								<td><?php esc_html_e( 'Raw EXIF camera model as provided by Google Photos', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>Canon EOS 5D Mark II</td>
								<td>⏳ <?php esc_html_e( 'May appear with delay', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							</tr>
							<tr>
								<td><code>{aperture}</code></td>
								<td><?php esc_html_e( 'Lens aperture (f-number)', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>ƒ/2.8</td>
								<td>⏳ <?php esc_html_e( 'May appear with delay', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							</tr>
							<tr>
								<td><code>{shutter}</code></td>
								<td><?php esc_html_e( 'Shutter speed', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>1/500s</td>
								<td>⏳ <?php esc_html_e( 'May appear with delay', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							</tr>
							<tr>
								<td><code>{focal}</code></td>
								<td><?php esc_html_e( 'Focal length', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>100mm</td>
								<td>⏳ <?php esc_html_e( 'May appear with delay', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							</tr>
							<tr>
								<td><code>{iso}</code></td>
								<td><?php esc_html_e( 'ISO sensitivity', 'janzeman-shared-albums-for-google-photos' ); ?></td>
								<td>ISO400</td>
								<td>⏳ <?php esc_html_e( 'May appear with delay', 'janzeman-shared-albums-for-google-photos' ); ?></td>
							</tr>
						</tbody>
					</table>

					<div class="jzsa-attention-box" style="margin-top: 16px;">
						<strong><?php esc_html_e( 'Note on delayed photo metadata', 'janzeman-shared-albums-for-google-photos' ); ?></strong>
						<p style="margin: 8px 0 0 0;"><?php esc_html_e( 'Some per-photo placeholders (description, camera, aperture, shutter, focal, ISO) are not available in the album listing. When you use any of them, the plugin automatically fetches each photo\'s individual page from Google Photos in the background to retrieve it. This means:', 'janzeman-shared-albums-for-google-photos' ); ?></p>
						<ul style="margin: 10px 0 0 0; padding-left: 22px; list-style: disc;">
							<li><?php esc_html_e( 'EXIF information is not perfectly reliable. Google decides what survives in a shared album, so availability depends a lot on the quality and origin of your album content.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
							<li><?php esc_html_e( 'It will appear with a brief delay the first time it is fetched (not instantly like date or dimensions). After that it is cached on your web server and appears immediately for later visitors.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
							<li><?php esc_html_e( 'The heavier load happens mainly during the first cache warm-up. For large albums, avoid clearing the plugin cache unless you actually need fresh Google Photos data.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
							<li><?php esc_html_e( 'Photos that were uploaded without EXIF (e.g. screenshots, edited exports) will show nothing for these placeholders.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
							<li><?php esc_html_e( 'If you don\'t use any EXIF placeholder, no background fetching occurs - zero overhead.', 'janzeman-shared-albums-for-google-photos' ); ?></li>
						</ul>
					</div>

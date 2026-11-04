# macOS Shared Album helper

`share-selected-photos-to-shared-album.js` prepares and adds photos one at a time to an iCloud Shared Album. The helper asks for the source and desired result, then selects the method automatically. Enter the choice number and press Return. The file picker is the only native dialog. Each prompt explains what the answer will do.

The helper is source code for technically comfortable Mac users. It is not a signed or notarized app. Read the source before running it and grant only the permissions required by the route you choose. It is not included in the WordPress plugin ZIP.

## Caption selection

For items in Photos Library, the Activity caption is chosen in this order:

1. Photos Caption (`description`)
2. Photos Title (`name`)
3. The file name without its extension, with underscores and hyphens changed to spaces

For direct disk files, the helper uses the readable file name stem. The dry-run shows the exact proposed text. The string `[no caption]` is never posted.

## Routes

- **Current Photos selection**: reads the selected items and their Photos metadata, then uses Photos app to share them individually. If nothing is selected, the helper opens Photos and waits while you select items and press Return in Terminal.
- **Files from disk**: choose whether the files should enter Photos Library. **Use Photos Library** imports the files and shares them through Photos app. **Keep files outside Photos Library** leaves them on disk and guides you through iCloud.com. In live mode, choosing Photos Library imports the files before the Photos share flow; the iCloud.com route has you upload each item manually.
- **iCloud.com route**: this is guided, not browser-automated. In your already-signed-in browser, upload one file at a time to the chosen Shared Album, then use Activity > the post's `…` menu > **Edit Caption**. Confirm in the helper only after the photo and caption appear in Activity. The helper does not control or verify the browser page itself.

Live sharing is the default. Add `--dry-run` to show the chosen result, method, and whether Photos Library import would happen without importing or sharing. For the Photos Library route, live mode imports after that choice, reads Photos Caption and Title, then enters each caption in the one-item Photos dialog. The helper clicks Photos' Add button only after Accessibility reports the exact proposed caption in the field.

The helper does not request or read your Apple Account password. You sign in to iCloud.com yourself for the guided web route.

For the Photos app route, the helper checks the target Shared Album before posting. It opens the album's All view, enables its All Items filter, and reads the selected album media. Keep Shared Albums expanded in the Photos sidebar and clear any search text. The original source list is captured before this navigation.

An item is skipped when its filename, capture timestamp, width, and height match an existing album item. Each skip is logged; remaining items continue normally. The check also prevents repeated matching items within the same run. Dry-run previews skips for items already available in Photos; disk files that have not been imported cannot be checked yet. Existing duplicates are left in place. The helper keeps no local attempt ledger.

This is a metadata match, not a comparison of file contents. Different files with identical metadata can be skipped, while renamed or modified copies may not match. The check uses album contents currently available in Photos; let iCloud finish syncing and avoid concurrent uploads. If membership cannot be verified, the Photos route stops before posting. The guided iCloud.com route does not perform this automatic check.

## Clear a Shared Album

To remove every photo or video post for one Shared Album, open **Shared Albums > Activity** in Photos, then run:

```sh
osascript -l JavaScript scripts/share-selected-photos-to-shared-album.js --album "Test" --clear-album
```

The helper processes matching Activity posts one at a time. For each post it opens `…` > **Delete**, then chooses Photos' **Delete Post and N Photo/Video** action, which removes the Activity post and its media from the Shared Album. The album and its subscribers remain. There is no additional terminal confirmation; use `--dry-run --album "Test" --clear-album` to see how many matching posts Photos exposes before deleting them. If the interface does not match the expected Activity post or Delete Post dialog, the helper stops.

Deleting posts from a Shared Album removes them for all participants. Copies they previously saved remain in their libraries. Album owners can delete posts from any participant; other participants can delete only their own posts.

## Permissions

- Dry-run with Photos Library selection reads Photos metadata and uses System Events and Accessibility to inspect the target album. It navigates Photos and changes its selection and filter, but does not import or share anything.
- Automated posting through Photos uses Photos automation, System Events automation, and Accessibility for the app running `osascript` (usually Terminal).
- The guided iCloud.com route does not automate the browser. You sign in yourself and upload/edit each item manually.

Never disable Gatekeeper or paste a command that downloads and runs code in one step. Save and inspect the helper, then run it directly with `osascript`.

## Test procedure

Use a dedicated Shared Album named `Test` and photos that are safe to post there. Do not use the real album for initial tests.

### 1. Check the command and dry-run behavior

From the public plugin repository root, run:

```sh
osascript -l JavaScript scripts/share-selected-photos-to-shared-album.js --help
```

Select three test photos in Photos, then run:

```sh
osascript -l JavaScript scripts/share-selected-photos-to-shared-album.js --dry-run --album "Test"
```

At the Terminal prompt, enter `1` for **Photos Library selection** and press Return. Verify that the count and order match your selection. If Caption and Title are empty, each proposed caption should be a readable file name stem marked `[from filename]`. The helper must report that nothing was shared, and Activity must remain unchanged.

### 2. Preview disk files without importing or uploading

Run the same dry-run command again. Press `2` for **Files from disk**, select two test files outside Photos Library in the file picker, then choose **Keep files outside Photos Library**. The helper should say it will use iCloud.com. Verify the no-import plan, file names, and proposed captions. Confirm that neither iCloud.com Activity nor Photos Library changed.

For the other dry-run plan, repeat with test files and choose **Use Photos Library**. Verify it selects Photos app and says the files would enter Photos Library first. Neither Photos Library nor Shared Album Activity should change.

### 3. Test the guided direct-web route with one file

Sign in to iCloud.com yourself and open the `Test` Shared Album. In Terminal, run:

```sh
osascript -l JavaScript scripts/share-selected-photos-to-shared-album.js --album "Test"
```

Press `2` for **Files from disk**, select one new file that has not been posted before, then choose **Keep files outside Photos Library**. The helper selects iCloud.com and shows the caption. Follow the Terminal instructions: upload exactly that one file, open Activity, use `…` > **Edit Caption**, and enter the proposed text. Press `1` for **Continue after verifying** only after you can see the one new Activity item with its caption. Then verify the same item and text on iCloud.com and confirm it did not appear in Photos Library.

If the helper stops after a live action, check Activity before deciding whether to retry. The guided web route does not record the attempt or determine whether the item is already in the album.

### 4. Test the Photos automation route separately

Only after the guided web route is correct, run the helper without `--dry-run` with a different test photo. Press `2` for **Files from disk**, select the file, then choose **Use Photos Library**. The import adds the file to Photos Library before sharing. The Photos automation opens the one-item Add dialog, enters the proposed caption, verifies the visible field value, clicks Add, and waits for that dialog to close before moving on. Verify that Activity shows one contribution with the expected caption.

## Reporting a failure

Copy the exact error, say which source and route you selected, and include whether the target item appears in Activity. Do not include your Apple Account password or other credentials.

The source and route prompts appear directly in Terminal; enter the number shown beside your choice and press Return. When choosing files from disk, the macOS file picker opens as a native dialog. Pressing Control-C during dry-run is safe because dry-run does not import or share anything. Do not interrupt after a live action if the result may be uncertain; check Activity before retrying.

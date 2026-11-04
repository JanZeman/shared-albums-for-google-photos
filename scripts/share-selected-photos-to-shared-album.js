#!/usr/bin/osascript -l JavaScript
/*
 * Add Photos selections or disk files one at a time to an iCloud Shared Album.
 * Live sharing is the default; --dry-run previews without changes. The iCloud.com route is user-guided.
 * Run with osascript -l JavaScript so choices can be entered as ordinary Terminal lines.
 */

ObjC.import("Foundation");
ObjC.import("AppKit");

// UI labels and timing are kept here so they are easy to update after macOS changes.
const UI = {
  photosAppPath: "/System/Applications/Photos.app",
  systemEventsPath: "/System/Library/CoreServices/System Events.app",
  processName: "Photos",
  imageMenu: "Image",
  addToSharedAlbumMenu: "Add to Shared Album",
  sharedAlbumsSidebar: "Shared Albums",
  sharedAlbumAllView: "All",
  albumFilterButton: "Filter",
  albumAllItemsFilter: "All Items",
  viewerToggleKey: " ",
  photoCaptionPlaceholder: "Comment on this photo (optional)",
  videoCaptionPlaceholder: "Comment on this video (optional)",
  captionDialogButtonCount: 3,
  pollSeconds: 0.5,
  captionDialogSettleSeconds: 1.5,
  uiTimeoutSeconds: 10,
  deleteDialogTimeoutSeconds: 10,
  quietPollsBeforeDone: 3
};

const ANSI_RED = "\u001b[31m";
const ANSI_RESET = "\u001b[0m";

function printDryRunNotice(isFinal, clearingAlbum) {
  const message = isFinal ?
    clearingAlbum ?
      "This was a dry run. Nothing was deleted." :
      "This was a dry run. Nothing was imported or shared." :
    clearingAlbum ?
      "This is a dry run. Nothing will be deleted." :
      "This is a dry run. Nothing will be imported or shared.";
  if (isFinal) console.log("");
  console.log(ANSI_RED + message + ANSI_RESET);
}

function sleep(seconds) {
  $.NSThread.sleepForTimeInterval(seconds);
}

function unwrap(value) {
  if (value === null || value === undefined) return "";
  try { return ObjC.unwrap(value); } catch (_) { return String(value); }
}

function filenameCaption(fileName) {
  if (!fileName || fileName === "(unknown filename)") return "";
  const lastComponent = fileName.split(/[\\/]/).pop();
  const stem = lastComponent.replace(/\.[^.]+$/, "");
  return stem.replace(/[_-]+/g, " ").replace(/\s+/g, " ").trim();
}

function activateDialogHost() {
  const application = $.NSApplication.sharedApplication;
  application.setActivationPolicy($.NSApplicationActivationPolicyRegular);
  application.activateIgnoringOtherApps(true);
}

function readTerminalLine() {
  const input = $.NSFileHandle.fileHandleWithStandardInput;
  let line = "";
  while (line.length < 4096) {
    const data = input.readDataOfLength(1);
    if (Number(data.length) === 0) {
      if (line.length === 0) throw new Error("No terminal response was received.");
      break;
    }
    const character = unwrap($.NSString.alloc.initWithDataEncoding(data, $.NSASCIIStringEncoding));
    if (character === "\n" || character === "\r") break;
    line += character;
  }
  if (line.length >= 4096) throw new Error("Terminal response was too long.");
  return line.trim();
}

function chooseOption(title, message, choices) {
  console.log("\n" + title);
  console.log(message);
  choices.forEach((choice, index) => console.log("  " + (index + 1) + ") " + choice));
  while (true) {
    console.log("Enter a number (1-" + choices.length + "): ");
    const response = readTerminalLine();
    if (!response) continue;
    const index = Number(response) - 1;
    if (/^\d+$/.test(response) && index >= 0 && index < choices.length) return index;
    console.log("Invalid choice.");
  }
}

function chooseFiles() {
  activateDialogHost();
  console.log("Waiting for the file picker.");
  const panel = $.NSOpenPanel.openPanel;
  panel.setCanChooseFiles(true);
  panel.setCanChooseDirectories(false);
  panel.setAllowsMultipleSelection(true);
  panel.setPrompt($("Choose Photos"));
  // JXA invokes this zero-argument AppKit selector when its property is read.
  const response = Number(panel.runModal);
  if (response !== 1) return [];
  const urls = panel.URLs;
  const paths = [];
  for (let i = 0; i < Number(urls.count); i++) {
    paths.push(ObjC.unwrap(urls.objectAtIndex(i).path));
  }
  return paths;
}

function chooseInput(photos, options) {
  const sourceChoice = chooseOption(
    "Choose photo source",
    "The Photos selection is already in Photos Library. With disk files, you choose Library handling first; the available album method follows from that choice.",
    ["Photos Library selection", "Files from disk", "Cancel"]
  );
  if (sourceChoice === 0) {
    let selectedInPhotos = photos.selection();
    while (!selectedInPhotos || selectedInPhotos.length === 0) {
      console.log("\nNo photos or videos are selected. Photos will open. Select one or more items, then return to Terminal and press Return. Press Control-C to cancel.");
      photos.activate();
      readTerminalLine();
      selectedInPhotos = photos.selection();
    }
    return { source: "photos", route: "photos", importToLibrary: false, items: selectedInPhotos, paths: [] };
  }
  if (sourceChoice !== 1) throw new Error("No source was selected.");

  const paths = chooseFiles();
  if (!paths.length) throw new Error("No files were selected.");
  if (paths.some(path => /[\t\r\n]/.test(path))) {
    throw new Error("A selected file path contains a tab or line break, which this helper cannot process.");
  }
  const libraryChoice = chooseOption(
    "Which path do you prefer?",
    "Use Photos Library and the Photos app, or keep the files outside the Library and use iCloud.com.",
    ["Use Photos Library", "Keep files outside Photos Library", "Cancel"]
  );
  if (libraryChoice === 2) throw new Error("Canceled before continuing.");

  if (libraryChoice === 1) {
    return { source: "files", route: "web", importToLibrary: false, items: [], paths };
  }

  return { source: "files", route: "photos", importToLibrary: true, items: [], paths };
}

let clearAccessibilityCache = null;

function attribute(element, name) {
  if (clearAccessibilityCache) {
    if (!clearAccessibilityCache.has(element)) {
      clearAccessibilityCache.set(element, element.properties());
    }
    const p = clearAccessibilityCache.get(element);
    if (name === "AXPlaceholderValue") {
      if (!Object.prototype.hasOwnProperty.call(p, "captionPlaceholder")) {
        try { p.captionPlaceholder = unwrap(element.attributes.byName(name).value()); }
        catch (_) { p.captionPlaceholder = ""; }
      }
      return p.captionPlaceholder;
    }
    const fields = { AXRole: "role", AXSubrole: "subrole", AXTitle: "title",
      AXDescription: "accessibilityDescription", AXValue: "value",
      AXHelp: "help", AXPosition: "position", AXSize: "size" };
    if (name === "AXDescription") return p.accessibilityDescription || p.description || "";
    return fields[name] ? (p[fields[name]] === null ? "" : p[fields[name]]) : "";
  }
  try { return unwrap(element.attributes.byName(name).value()); }
  catch (_) { return ""; }
}

function dialogElements(root) {
  const elements = [];
  const deadline = Date.now() + UI.uiTimeoutSeconds * 1000;
  function visit(element, depth) {
    if (elements.length >= 100 || Date.now() > deadline) {
      throw new Error("Caption dialog inspection exceeded its time or control limit; Add was not clicked.");
    }
    elements.push(element);
    if (depth > 0) element.uiElements().forEach(child => visit(child, depth - 1));
  }
  visit(root, 5);
  return elements;
}

function captionDialogScopes(process) {
  const windows = process.windows();
  const sheets = [];
  for (let i = 0; i < windows.length; i++) {
    try {
      const windowSheets = windows[i].sheets();
      for (let j = 0; j < windowSheets.length; j++) sheets.push(windowSheets[j]);
    } catch (_) {}
  }
  // Do not fall back to the full library window when a modal is absent.
  return sheets;
}

function deleteDialogScopes(process) {
  // The Delete Post confirmation is a small modal, not part of the Activity
  // window's large content tree. Scanning the main Photos window can block for
  // minutes, and a sheet on that window must not hide a separate alert window.
  const scopes = [];
  const descriptions = [];
  const windows = process.windows();
  for (let i = 0; i < windows.length; i++) {
    const window = windows[i];
    const role = attribute(window, "AXRole");
    const subrole = attribute(window, "AXSubrole");
    const title = labelsFor(window).join(" ");
    const description = attribute(window, "AXDescription");
    const isDialog = subrole === "AXDialog" ||
      /^alert$/i.test(description) || /Delete Post\?/i.test(title);
    if (isDialog) scopes.push(window);
    descriptions.push([role || "?", subrole || "?", description || "?", title || "(untitled)"].join("/"));
    try {
      const sheets = window.sheets();
      for (let j = 0; j < sheets.length; j++) scopes.push(sheets[j]);
    } catch (_) {}
  }
  return { scopes, descriptions };
}

function elementPosition(element) {
  const point = attribute(element, "AXPosition");
  try {
    const x = Number(Array.isArray(point) ? point[0] : point.x);
    const y = Number(Array.isArray(point) ? point[1] : point.y);
    if (Number.isFinite(x) && Number.isFinite(y)) return { x, y };
  } catch (_) {}
  return null;
}

function elementSize(element) {
  const size = attribute(element, "AXSize");
  try {
    const width = Number(Array.isArray(size) ? size[0] : size.width);
    const height = Number(Array.isArray(size) ? size[1] : size.height);
    if (Number.isFinite(width) && Number.isFinite(height)) return { width, height };
  } catch (_) {}
  return null;
}

function findCaptionDialog(process, albumName) {
  const previousCache = clearAccessibilityCache;
  clearAccessibilityCache = new Map();
  try { return inspectCaptionDialog(process, albumName); }
  finally { clearAccessibilityCache = previousCache; }
}

function inspectCaptionDialog(process, albumName) {
  const expectedHeadings = ["Add 1 Photo to " + albumName, "Add 1 Video to " + albumName];
  const scopes = captionDialogScopes(process);
  const matches = [];
  let details = {
    headingFound: false,
    dialogHeadings: [],
    captionFields: 0,
    captionPlaceholders: [],
    buttons: 0,
    buttonPositions: "unavailable"
  };
  for (let i = 0; i < scopes.length; i++) {
    const elements = dialogElements(scopes[i]);
    let headingFound = false;
    let conflictingHeading = false;
    const dialogHeadings = [];
    const captionFields = [];
    const captionPlaceholders = [];
    const buttons = [];
    for (const element of elements) {
      const role = attribute(element, "AXRole");
      if (role === "AXTextField" || role === "AXTextArea") {
        const placeholder = attribute(element, "AXPlaceholderValue");
        if (typeof placeholder === "string") {
          if (placeholder.indexOf(UI.photoCaptionPlaceholder) === 0 ||
              placeholder.indexOf(UI.videoCaptionPlaceholder) === 0) {
            captionFields.push({ element, placeholder });
            captionPlaceholders.push(placeholder);
          }
        }
      } else if (role === "AXButton") {
        buttons.push(element);
      } else if (role === "AXStaticText") {
        // Most Photos versions expose the dialog heading as a static text value.
        const value = attribute(element, "AXValue");
        if (typeof value === "string") {
          if (/^Add \d+ (Photos?|Videos?) to /.test(value)) {
            dialogHeadings.push(value);
            if (expectedHeadings.indexOf(value) !== -1) headingFound = true;
            else conflictingHeading = true;
          }
        }
      }
    }
    const positionedButtons = buttons.map(element => ({ element, position: elementPosition(element) }));
    const validPositions = positionedButtons.every(button => button.position !== null);
    const yValues = validPositions ? positionedButtons.map(button => button.position.y) : [];
    const sameButtonRow = validPositions && yValues.length > 0 &&
      Math.max.apply(null, yValues) - Math.min.apply(null, yValues) <= 24;
    const addButton = sameButtonRow ? positionedButtons.sort((left, right) =>
      right.position.x - left.position.x
    )[0].element : null;
    details = {
      headingFound,
      dialogHeadings,
      captionFields: captionFields.length,
      captionPlaceholders,
      buttons: buttons.length,
      buttonPositions: validPositions ? positionedButtons.map(button =>
        Math.round(button.position.x) + "," + Math.round(button.position.y)
      ).join(" ") : "unavailable"
    };
    // This Photos version exposes the comment box as AXTextField, with three
    // same-row buttons: Options, Cancel, and Add. The rightmost is Add.
    const expectedPlaceholder = dialogHeadings.some(heading =>
      heading.indexOf("Add 1 Video to ") === 0
    ) ? UI.videoCaptionPlaceholder : UI.photoCaptionPlaceholder;
    const matchingCaptionFields = captionFields.filter(field =>
      field.placeholder.indexOf(expectedPlaceholder) === 0
    );
    if (headingFound && !conflictingHeading && matchingCaptionFields.length === 1 &&
        captionFields.length === 1 &&
        buttons.length === UI.captionDialogButtonCount && sameButtonRow) {
      matches.push({ field: matchingCaptionFields[0].element, addButton });
    }
  }
  const match = matches.length === 1 ? matches[0] : null;
  return {
    field: match ? match.field : null,
    addButton: match ? match.addButton : null,
    matchCount: matches.length,
    details
  };
}

function waitForCaptionDialog(process, albumName, fileName) {
  sleep(UI.captionDialogSettleSeconds);
  const state = findCaptionDialog(process, albumName);
  if (state.field) return state;
  throw new Error("One-photo caption dialog for '" + albumName +
    "' was not verified for " + fileName + " (album heading found: " +
    (state.details.headingFound ? "yes" : "no") + "; caption fields: " +
    state.details.captionFields + "; dialog heading: " +
    (state.details.dialogHeadings.join(" | ") || "not exposed") +
    "; caption placeholders: " +
    (state.details.captionPlaceholders.join(" | ") || "not exposed") +
    "; AXButtons: " + state.details.buttons +
    "; button positions: " + state.details.buttonPositions +
    "; matching sheets/windows: " + state.matchCount +
    "). Stopped before typing or clicking Add.");
}

function waitForDialogToClose(process, fileName, albumName) {
  const deadline = Date.now() + UI.uiTimeoutSeconds * 1000;
  let quietPolls = 0;
  while (Date.now() < deadline) {
    const windows = process.windows();
    let sheetCount = 0;
    for (let i = 0; i < windows.length; i++) {
      try { sheetCount += windows[i].sheets().length; } catch (_) {}
    }
    const dialogStillPresent = sheetCount > 0;
    quietPolls = dialogStillPresent ? 0 : quietPolls + 1;
    if (quietPolls >= UI.quietPollsBeforeDone) return;
    sleep(UI.pollSeconds);
  }
  throw new Error("Post status is uncertain for " + fileName + " in '" + albumName +
    "'. Check Activity before deciding whether to retry.");
}

function allProcessElements(process) {
  const elements = [];
  const windows = process.windows();
  function walk(root, depth) {
    if (elements.length >= 500) throw new Error("Activity inspection exceeded 500 controls.");
    elements.push(root);
    if (depth > 0) root.uiElements().forEach(child => walk(child, depth - 1));
  }
  for (const window of windows) {
    if (attribute(window, "AXSubrole") !== "AXStandardWindow") continue;
    elements.push(window);
    const split = window.uiElements().find(e => attribute(e, "AXRole") === "AXSplitGroup");
    if (!split) continue;
    // System Events exposes Activity inside AXHostingView, beside the sidebar.
    // Keep direct child references; flattened entireContents references can fail on click.
    const hosting = split.uiElements().filter(e => attribute(e, "AXSubrole") === "AXHostingView");
    hosting.forEach(root => walk(root, 5));
  }
  return { windows, elements };
}

function labelsFor(element) {
  return ["AXTitle", "AXDescription", "AXValue", "AXHelp", "AXLabel"]
    .map(name => attribute(element, name))
    .filter(value => typeof value === "string" && value.length > 0);
}

function isSharedPostHeading(value) {
  return typeof value === "string" && /\bshared\b/i.test(value) &&
    /\b(?:photo|photos|video|videos)\b/i.test(value) && /\bto\b/i.test(value);
}

function normalizedActivityHeading(value) {
  return value
    .replace(/\u00a0/g, " ")
    .replace(/\s*\n\s*\d+\s*(?:sec(?:ond)?s?|min(?:ute)?s?\.?|h(?:our)?s?|d(?:ay)?s?)\s+ago\s*$/i, "")
    .replace(/[\s>›»→]+$/, "")
    .trim();
}

function headingMatchesAlbum(value, albumName) {
  const normalized = normalizedActivityHeading(value).toLowerCase();
  const suffix = (" to " + albumName).toLowerCase();
  return normalized.endsWith(suffix) || normalized.endsWith(suffix + " forward");
}

function redactActivityAuthor(value) {
  return value.replace(/^.*?\bshared\b/i, "[participant] shared").slice(0, 180);
}

function readActivityState(process, albumName) {
  clearAccessibilityCache = new Map();
  const snapshot = allProcessElements(process);
  let activityHeaderVisible = false;
  for (const window of snapshot.windows) {
    const windowTitle = labelsFor(window).join(" ");
    if (/\bActivity\b/i.test(windowTitle)) {
      activityHeaderVisible = true;
      break;
    }
    const windowPosition = elementPosition(window);
    const windowSize = elementSize(window);
    const mainPaneStartX = windowPosition && windowSize ?
      windowPosition.x + windowSize.width * 0.4 : 500;
    const hasMainPaneActivityTitle = snapshot.elements.some(element => {
      if (!labelsFor(element).some(label => label === "Activity")) return false;
      const position = elementPosition(element);
      return position && position.x >= mainPaneStartX;
    });
    if (hasMainPaneActivityTitle) {
      activityHeaderVisible = true;
      break;
    }
  }
  const headings = [];
  const seen = {};
  const diagnosticLabels = [];
  const diagnosticSeen = {};
  const roleCounts = {};
  for (const element of snapshot.elements) {
    const role = attribute(element, "AXRole") || "(no AXRole)";
    roleCounts[role] = (roleCounts[role] || 0) + 1;
    const labels = labelsFor(element);
    for (const label of labels) {
      const lower = label.toLowerCase();
      if (lower.includes(albumName.toLowerCase()) ||
          (/\bshared\b/i.test(label) && /\b(?:photo|photos|video|videos)\b/i.test(label))) {
        const safeLabel = redactActivityAuthor(label);
        const diagnosticKey = role + "|" + safeLabel;
        if (!diagnosticSeen[diagnosticKey]) {
          diagnosticSeen[diagnosticKey] = true;
          diagnosticLabels.push(role + ": " + safeLabel);
        }
      }
    }
    const value = labels.find(isSharedPostHeading);
    if (!value) continue;
    const position = elementPosition(element);
    const key = value + "|" + (position ? Math.round(position.y) : "unknown");
    if (seen[key]) continue;
    seen[key] = true;
    headings.push({ element, value, position });
  }
  headings.sort((left, right) => {
    if (!left.position || !right.position) return 0;
    return left.position.y - right.position.y;
  });
  const targetPosts = headings.filter(post =>
    headingMatchesAlbum(post.value, albumName)
  );
  return {
    activityVisible: activityHeaderVisible || headings.length > 0,
    headings,
    targetPosts,
    elements: snapshot.elements,
    diagnosticLabels,
    roleCounts
  };
}

function activityMoreButton(state, post) {
  if (!post.position) throw new Error("Photos did not expose the Activity post position.");
  const nextHeading = state.headings.find(candidate => candidate.position &&
    candidate.position.y > post.position.y + 1
  );
  const upperY = nextHeading ? nextHeading.position.y : post.position.y + 1200;
  const buttons = state.elements.filter(element => {
    const role = attribute(element, "AXRole");
    if (role !== "AXButton" && role !== "AXMenuButton") return false;
    const position = elementPosition(element);
    return position && position.y > post.position.y && position.y < upperY;
  });
  const namedMoreButtons = buttons.filter(button => labelsFor(button).some(label =>
    /^(more|more options|more actions|additional options|options)$/i.test(label.trim())
  ));
  if (namedMoreButtons.length === 1) return namedMoreButtons[0];
  if (namedMoreButtons.length > 1) {
    throw new Error("Photos exposed multiple More buttons for this Activity post.");
  }

  const rows = [];
  for (const button of buttons) {
    const position = elementPosition(button);
    let row = rows.find(candidate => Math.abs(candidate.y - position.y) <= 20);
    if (!row) {
      row = { y: position.y, buttons: [] };
      rows.push(row);
    }
    row.buttons.push({ element: button, x: position.x });
  }
  const actionRows = rows.filter(row => row.buttons.length === 3)
    .sort((left, right) => right.y - left.y);
  if (actionRows.length !== 1) {
    throw new Error("Could not identify one Activity action row with its three controls.");
  }
  actionRows[0].buttons.sort((left, right) => left.x - right.x);
  return actionRows[0].buttons[2].element;
}

function findDeleteMenuItem(moreButton) {
  // The open Options menu is a child of this menu button. Search only that
  // small subtree instead of traversing every control in the Photos window.
  clearAccessibilityCache = new Map();
  const menus = moreButton.uiElements().filter(e => attribute(e, "AXRole") === "AXMenu");
  if (menus.length !== 1) return null;
  const item = menus[0].menuItems.byName("Delete");
  return item.exists() ? item : null;
}

function findDeletePostDialog(process) {
  clearAccessibilityCache = new Map();
  const candidates = deleteDialogScopes(process);
  const scopes = candidates.scopes;
  const matches = [];
  let details = {
    heading: false,
    deleteButtons: [],
    cancelButtons: 0,
    candidates: candidates.descriptions
  };
  for (const scope of scopes) {
    const elements = [scope].concat(scope.uiElements());
    // Photos exposes the heading and body as one text element, for example:
    // "Delete Post? Do you want to delete this post and all content within?..."
    const heading = elements.some(element => labelsFor(element).some(label =>
      /^Delete Post\?/i.test(label.trim())
    ));
    const buttons = elements.filter(element => attribute(element, "AXRole") === "AXButton");
    const deleteButtons = buttons.filter(button => labelsFor(button).some(label =>
      /^Delete Post and \d+ (?:Photos?|Videos?)$/i.test(label.trim())
    ));
    const cancelButtons = buttons.filter(button => labelsFor(button).some(label =>
      label.trim() === "Cancel"
    ));
    details = {
      heading,
      deleteButtons: deleteButtons.flatMap(labelsFor),
      cancelButtons: cancelButtons.length,
      candidates: candidates.descriptions
    };
    if (heading && deleteButtons.length === 1 && cancelButtons.length === 1) {
      matches.push(deleteButtons[0]);
    }
  }
  return { button: matches.length === 1 ? matches[0] : null, details };
}

function waitForDeletePostDialog(process) {
  const deadline = Date.now() + UI.deleteDialogTimeoutSeconds * 1000;
  let state = findDeletePostDialog(process);
  while (!state.button && Date.now() < deadline) {
    sleep(UI.pollSeconds);
    state = findDeletePostDialog(process);
  }
  if (!state.button) {
    throw new Error("Photos' Delete Post dialog was not verified (heading: " +
      (state.details.heading ? "yes" : "no") + "; delete buttons: " +
      (state.details.deleteButtons.join(" | ") || "none") +
      "; Cancel buttons: " + state.details.cancelButtons +
      "; modal candidates: " + (state.details.candidates.join(" | ") || "none") +
      "). Nothing was confirmed.");
  }
  return state.button;
}

function waitForPostRemoval(process, albumName, previousCount, postText) {
  const deadline = Date.now() + UI.deleteDialogTimeoutSeconds * 1000;
  while (Date.now() < deadline) {
    const state = readActivityState(process, albumName);
    if (state.targetPosts.length < previousCount) return;
    sleep(UI.pollSeconds);
  }
  throw new Error("The Activity entry did not disappear after Delete for '" + postText +
    "'. Stop and inspect Photos before continuing.");
}

function clearAlbumActivity(options) {
  clearAccessibilityCache = new Map();
  const events = Application(UI.systemEventsPath);
  const process = events.processes.byName(UI.processName);
  process.frontmost = true;
  const started = Date.now();
  console.log("Reading Activity...");
  const initial = readActivityState(process, options.album);
  console.log("Activity read in " + ((Date.now() - started) / 1000).toFixed(1) + "s.");
  if (!initial.activityVisible) {
    throw new Error("Open Shared Albums > Activity in Photos, then rerun with --album '" +
      options.album + "' --clear-album.");
  }
  if (options.mode === "dry-run") {
    if (initial.targetPosts.length) {
      console.log("Dry-run: " + initial.targetPosts.length + " Activity post(s) for '" +
        options.album + "' match the clear operation.");
      initial.targetPosts.forEach((post, index) => {
        try {
          activityMoreButton(initial, post);
          console.log("  " + (index + 1) + ". Activity menu control found.");
        } catch (error) {
          console.log("  " + (index + 1) + ". Activity menu control not verified: " + error.message);
        }
      });
    } else {
      console.log("Dry-run: no Activity posts for '" + options.album + "' were recognized. Nothing would be deleted.");
      console.log("Relevant Accessibility labels: " +
        (initial.diagnosticLabels.join(" | ") || "none"));
      console.log("Accessibility roles: " + Object.keys(initial.roleCounts).sort().map(role =>
        role + "=" + initial.roleCounts[role]
      ).join(", "));
    }
    printDryRunNotice(true, true);
    return;
  }

  if (!initial.targetPosts.length) {
    throw new Error("No Activity posts for '" + options.album +
      "' were recognized. Nothing was deleted. Run --dry-run to inspect the Accessibility labels.");
  }

  let deleted = 0;
  let state = initial;
  while (true) {
    if (!state.targetPosts.length) break;
    const post = state.targetPosts[0];
    if (!post.position) {
      throw new Error("Photos did not expose the Activity post position; nothing was deleted.");
    }
    let step = "open the Activity post menu";
    try {
      const moreButton = activityMoreButton(state, post);
      moreButton.click();
      const deadline = Date.now() + UI.deleteDialogTimeoutSeconds * 1000;
      let deleteMenuItem = findDeleteMenuItem(moreButton);
      while (!deleteMenuItem && Date.now() < deadline) {
        sleep(UI.pollSeconds);
        deleteMenuItem = findDeleteMenuItem(moreButton);
      }
      if (!deleteMenuItem) {
        throw new Error("Photos did not expose the Delete action for this Activity post. Check album ownership or permissions.");
      }
      step = "open Delete Post confirmation";
      deleteMenuItem.click();
      step = "verify the Delete Post confirmation";
      const deleteButton = waitForDeletePostDialog(process);
      step = "delete the Activity post and its album media";
      deleteButton.click();
      step = "verify Activity post removal";
      waitForPostRemoval(process, options.album, state.targetPosts.length, post.value);
      deleted++;
      console.log("Deleted Activity post " + deleted + " from '" + options.album + "'.");
      state = readActivityState(process, options.album);
    } catch (error) {
      throw new Error("Stopped after " + deleted + " deletion(s); step: " + step +
        ". " + error.message);
    }
  }
  console.log("Cleared " + deleted + " Activity post(s) from '" + options.album + "'.");
}

function parseArguments(argv) {
  let mode = "live";
  let album = "";
  let clearAlbum = false;
  for (let i = 0; i < argv.length; i++) {
    if (argv[i] === "--dry-run") mode = "dry-run";
    else if (argv[i] === "--album" && i + 1 < argv.length) album = argv[++i];
    else if (argv[i] === "--clear-album") clearAlbum = true;
    else throw new Error("Unknown or incomplete option: " + argv[i]);
  }
  if (!album) throw new Error("Pass the target Shared Album with --album NAME.");
  if (/[\t\r\n]/.test(album)) throw new Error("Album name cannot contain tabs or line breaks.");
  return { mode, album, clearAlbum };
}

function mediaFingerprint(item) {
  const filename = String(item.filename() || "");
  const date = item.date();
  const captured = date ? new Date(date).getTime() : NaN;
  const width = Number(item.width());
  const height = Number(item.height());
  if (!filename || !Number.isFinite(captured) || width <= 0 || height <= 0 ||
      !Number.isFinite(width) || !Number.isFinite(height)) {
    throw new Error("Could not read complete duplicate-check metadata; stopped before sharing.");
  }
  return JSON.stringify([filename, captured, width, height]);
}

function readSharedAlbumMembership(photos, events, process, albumName) {
  const started = Date.now();
  console.log("Checking existing items in '" + albumName + "'...");
  process.frontmost = true;
  const props = new Map();
  function property(element) {
    if (!props.has(element)) props.set(element, element.properties());
    return props.get(element);
  }
  // Keep indexed selectors: returned window objects are named by their mutable title.
  function children(root) {
    const count = root.uiElements.length;
    const result = [];
    for (let i = 0; i < count; i++) result.push(root.uiElements[i]);
    return result;
  }
  const windows = [];
  const windowCount = process.windows.length;
  for (let i = 0; i < windowCount; i++) {
    if (property(process.windows[i]).subrole === "AXStandardWindow") windows.push(process.windows[i]);
  }
  if (windows.length !== 1) throw new Error("Expected one Photos library window for the album check.");
  const window = windows[0];
  const split = children(window).find(e => property(e).role === "AXSplitGroup");
  if (!split) throw new Error("Photos did not expose its sidebar; stopped before sharing.");
  const sidebar = children(split).find(e => property(e).role === "AXScrollArea");
  const outline = sidebar && children(sidebar).find(e => property(e).role === "AXOutline");
  if (!outline) throw new Error("Show the Photos sidebar and expand Shared Albums, then retry.");
  const matches = [];
  let inSharedAlbums = false;
  for (let rowIndex = 0; rowIndex < outline.rows.length; rowIndex++) {
    const row = outline.rows[rowIndex];
    const fields = [];
    for (const cell of children(row)) {
      for (const child of children(cell)) fields.push({ element: child, p: property(child) });
    }
    if (fields.some(f => f.p.value === UI.sharedAlbumsSidebar)) inSharedAlbums = true;
    if (inSharedAlbums && fields.some(f => f.p.role === "AXTextField" && f.p.value === albumName)) {
      matches.push(row);
    }
  }
  if (matches.length !== 1) {
    throw new Error("Expand Shared Albums in the Photos sidebar so exactly one '" + albumName + "' album is visible.");
  }
  matches[0].selected = true;
  sleep(UI.pollSeconds);
  props.clear();
  const title = String(property(window).title || "");
  if (!(title === albumName || title.indexOf(albumName + " ") === 0) || !matches[0].selected()) {
    throw new Error("Could not verify the target Shared Album; stopped before sharing.");
  }
  const toolbar = children(window).find(e => property(e).role === "AXToolbar");
  const controls = [];
  function inspectControls(root, depth) {
    for (const child of children(root)) {
      controls.push(child);
      if (depth > 0 && ["AXGroup", "AXRadioGroup"].indexOf(property(child).role) !== -1) {
        inspectControls(child, depth - 1);
      }
    }
  }
  if (toolbar) inspectControls(toolbar, 3);
  const allViews = controls.filter(e => property(e).role === "AXRadioButton" &&
    (property(e).accessibilityDescription || property(e).description) === UI.sharedAlbumAllView);
  if (allViews.length !== 1) throw new Error("Photos did not expose the Shared Album All view.");
  if (Number(property(allViews[0]).value) !== 1) allViews[0].click();
  const searches = controls.filter(e => ["AXTextField", "AXSearchField"].indexOf(property(e).role) !== -1);
  if (searches.some(e => typeof property(e).value === "string" && property(e).value.trim())) {
    throw new Error("Clear the Photos search field before checking album duplicates.");
  }
  const filters = controls.filter(e => property(e).role === "AXCheckBox" &&
    property(e).accessibilityDescription === UI.albumFilterButton);
  if (filters.length !== 1) throw new Error("Could not verify the album filter.");
  filters[0].click();
  try {
    const filterControls = [];
    function inspectFilter(root, depth) {
      if (filterControls.length >= 60) throw new Error("Unexpected Photos filter structure.");
      filterControls.push(root);
      if (depth > 0) children(root).forEach(e => inspectFilter(e, depth - 1));
    }
    inspectFilter(filters[0], 3);
    const allItems = filterControls.filter(e => property(e).role === "AXCheckBox" &&
      property(e).accessibilityDescription === UI.albumAllItemsFilter);
    if (allItems.length !== 1) throw new Error("Could not verify the All Items album filter.");
    if (property(allItems[0]).value !== true && Number(property(allItems[0]).value) !== 1) {
      allItems[0].click();
      props.delete(allItems[0]);
      if (property(allItems[0]).value !== true && Number(property(allItems[0]).value) !== 1) {
        throw new Error("Could not enable the All Items album filter.");
      }
    }
  } finally { events.keyCode(53); }
  sleep(UI.pollSeconds);
  props.clear();
  const grids = [];
  function findGrid(root, depth) {
    if (property(root).subrole === "AXCollectionList") { grids.push(root); return; }
    if (depth > 0) for (const child of children(root)) {
      if (property(child).role !== "AXScrollArea") findGrid(child, depth - 1);
    }
  }
  findGrid(split, 3);
  if (grids.length !== 1) throw new Error("Could not identify the target album's media grid.");
  grids[0].focused = true;
  if (!grids[0].focused()) throw new Error("Could not focus the target album's media grid.");
  events.keystroke("a", { using: ["command down"] });
  sleep(UI.pollSeconds);
  const members = photos.selection();
  if (!Array.isArray(members)) throw new Error("Photos did not return a verifiable album selection.");
  const fingerprints = new Set();
  for (const item of members) fingerprints.add(mediaFingerprint(item));
  console.log("Checked " + members.length + " existing album item(s) in " +
    ((Date.now() - started) / 1000).toFixed(1) + "s.");
  return fingerprints;
}

function main(argv) {
  const options = parseArguments(argv);
  if (options.mode === "dry-run") printDryRunNotice(false, options.clearAlbum);
  if (options.clearAlbum) {
    clearAlbumActivity(options);
    return;
  }
  const photos = Application(UI.photosAppPath);
  const input = chooseInput(photos, options);

  if (input.importToLibrary && options.mode === "live") {
    const importedItems = photos.import(input.paths.map(path => Path(path)));
    if (!importedItems || importedItems.length !== input.paths.length) {
      throw new Error("Photos did not confirm importing every selected file. Check Photos before continuing.");
    }
    input.items = importedItems;
  }

  const records = [];
  if (input.source === "photos" || input.items.length > 0) {
    for (let i = 0; i < input.items.length; i++) {
      const item = input.items[i];
      const id = input.route === "web" ? input.paths[i] : unwrap(item.id());
      const fileName = input.source === "files" ? input.paths[i].split(/[\\/]/).pop() :
        unwrap(item.filename()) || "(unknown filename)";
      const description = unwrap(item.description()).trim();
      const title = unwrap(item.name()).trim();
      const fileCaption = filenameCaption(fileName);
      const caption = description || title || fileCaption;
      const captionSource = description ? "Caption" : title ? "Title" : fileCaption ? "filename" : "none";
      if (!id) throw new Error("Photos did not provide a stable ID for " + fileName + ".");
      if (!caption) throw new Error("No Caption, Title, or usable filename for " + fileName + ".");
      if (/[\u0000-\u001f\u007f]/.test(caption)) {
        throw new Error("Caption contains control characters that cannot be safely typed into Photos.");
      }
      records.push({ item, id, fileName, caption, captionSource, fingerprint: mediaFingerprint(item) });
    }
  } else {
    input.paths.forEach(path => {
      if (/[\t\r\n]/.test(path)) throw new Error("A selected file path contains a tab or line break.");
      const fileName = path.split(/[\\/]/).pop();
      const caption = filenameCaption(fileName);
      if (!caption) throw new Error("No usable filename for selected file.");
      if (/[\u0000-\u001f\u007f]/.test(caption)) {
        throw new Error("Caption contains control characters that cannot be safely typed into Photos.");
      }
      records.push({ path, id: path, fileName, caption, captionSource: "filename" });
    });
  }

  let existing = new Set();
  if (input.route === "photos" && records.some(record => record.fingerprint)) {
    const events = Application(UI.systemEventsPath);
    existing = readSharedAlbumMembership(photos, events, events.processes.byName(UI.processName), options.album);
  }
  const itemSource = input.source === "photos" ? "selected from Photos Library" : "files from disk";
  const method = input.route === "web" ? "iCloud.com" : "Photos app";
  const libraryNote = input.source === "photos" ? "" : input.importToLibrary ?
    (options.mode === "live" ? "; imported into Photos Library" : "; would import into Photos Library") :
    "; staying out of Photos Library";
  console.log("");
  console.log((options.mode === "dry-run" ? "Summary" : "Plan") + " for '" + options.album + "': " +
    records.length + " " + itemSource + " -> " + method + libraryNote + ".");
  records.forEach((record, index) => {
    console.log((index + 1) + ". " + record.fileName + " -> " +
      record.caption + " [from " + record.captionSource + "]");
  });
  if (options.mode === "dry-run") {
    const preview = new Set(existing);
    for (const record of records) {
      if (record.fingerprint && preview.has(record.fingerprint)) {
        console.log("Skipped: " + record.fileName + (existing.has(record.fingerprint) ?
          " - already present in '" + options.album + "'." : " - repeated in this selection."));
      } else if (record.fingerprint) preview.add(record.fingerprint);
    }
    printDryRunNotice(true);
    return;
  }

  if (input.route === "web") {
    runGuidedWebRoute(records, options.album);
    return;
  }

  const events = Application(UI.systemEventsPath);
  const process = events.processes.byName(UI.processName);
  let added = 0;
  let skipped = 0;
  for (let recordIndex = 0; recordIndex < records.length; recordIndex++) {
    const record = records[recordIndex];
    if (existing.has(record.fingerprint)) {
      skipped++;
      console.log("Skipped: " + record.fileName + " - already present in '" + options.album + "'.");
      continue;
    }
    let step = "activate Photos";
    try {
      photos.activate();
      step = "read the current Photos selection";
      let focusedSelection = photos.selection();
      const alreadySelected = focusedSelection.length === 1 &&
        unwrap(focusedSelection[0].id()) === record.id;
      if (!alreadySelected) {
        step = "focus this item in Photos";
        const targetItem = photos.mediaItems.byId(record.id);
        if (!targetItem || unwrap(targetItem.id()) !== record.id) {
          throw new Error("Photos could not resolve this item by its library ID.");
        }
        photos.spotlight(targetItem);
        sleep(1);
        // Spotlight opens Photos Viewer. Space closes it and returns to the
        // grid, where selection() can be queried reliably before sharing.
        step = "close Photos Viewer after Spotlight";
        process.frontmost = true;
        events.keystroke(UI.viewerToggleKey, { using: [] });
        sleep(UI.pollSeconds);
        step = "verify the focused Photos selection";
        focusedSelection = photos.selection();
        if (focusedSelection.length !== 1 || unwrap(focusedSelection[0].id()) !== record.id) {
          throw new Error("Photos did not return to a single selection of the target item; stopped before sharing.");
        }
      }
      step = "bring Photos to the front";
      process.frontmost = true;
      step = "read the Photos menu bar";
      const menuBar = process.menuBars[0];
      step = "find the Image menu";
      const imageMenuItem = menuBar.menuBarItems.byName(UI.imageMenu);
      step = "open the Image menu";
      imageMenuItem.click();
      sleep(UI.pollSeconds);
      step = "find Add to Shared Album in the Image menu";
      const imageMenu = imageMenuItem.menus[0];
      const addToSharedAlbumMenuItem = imageMenu.menuItems.byName(UI.addToSharedAlbumMenu);
      step = "open Add to Shared Album from the Image menu";
      addToSharedAlbumMenuItem.click();
      sleep(UI.pollSeconds);

      step = "find the target album in the Add to Shared Album submenu";
      const albumSubmenu = addToSharedAlbumMenuItem.menus[0];
      const targetAlbumMenuItem = albumSubmenu.menuItems.byName(options.album);
      step = "choose the target album";
      targetAlbumMenuItem.click();

      step = "verify the one-photo dialog and enter the Activity caption";
      const dialog = waitForCaptionDialog(process, options.album, record.fileName);
      const field = dialog.field;
      step = "focus the Activity caption field";
      process.frontmost = true;
      field.click();
      field.focused = true;
      sleep(UI.pollSeconds);
      if (!field.focused()) {
        throw new Error("Photos did not focus the Activity caption field; Add was not clicked.");
      }
      step = "type the Activity caption";
      // System Events exposes keystroke as an application command with named
      // parameters passed in an object; Photos is already the frontmost process.
      events.keystroke(record.caption, { using: [] });
      sleep(UI.pollSeconds);
      const displayedCaption = attribute(field, "AXValue");
      if (typeof displayedCaption !== "string" || displayedCaption !== record.caption) {
        throw new Error("Photos did not display the expected caption in the Activity field; Add was not clicked.");
      }
      console.log("Caption verified in Photos. Adding the item.");

      step = "verify the Activity caption immediately before Add";
      const captionBeforeAdd = attribute(field, "AXValue");
      if (typeof captionBeforeAdd !== "string" || captionBeforeAdd !== record.caption) {
        throw new Error("The Activity caption changed before Add; the item was not submitted.");
      }

      step = "click Add";
      dialog.addButton.click();

      step = "wait for the sharing dialog to close";
      waitForDialogToClose(process, record.fileName, options.album);
      added++;
      existing.add(record.fingerprint);
    } catch (error) {
      throw new Error("Stopped at item " + (recordIndex + 1) + " of " + records.length +
        ": " + record.fileName + "; step: " + step + ". " + error.message +
        (step === "click Add" || step === "wait for the sharing dialog to close" ?
          " Submission may have succeeded. Check Activity before retrying this item." : ""));
    }
  }

  console.log("Added: " + added + "; skipped: " + skipped + "; selected: " + records.length +
    ". Album: '" + options.album + "'.");
}

function runGuidedWebRoute(records, albumName) {
  let completed = 0;
  for (const record of records) {
    const choice = chooseOption(
      "Upload one file to the Shared Album",
      "In your browser, add only this file to '" + albumName + "':\n\n" + record.fileName +
        "\n\nThen open Activity, choose the post's … menu > Edit Caption, and enter:\n\n" +
        record.caption +
        "\n\nChoose Continue only after the upload and caption edit are visible in Activity.",
      ["Continue after verifying", "Stop here"]
    );
    if (choice !== 0) {
      throw new Error("Stopped before confirming " + record.fileName +
        ". Check Activity before deciding whether to retry.");
    }
    completed++;
  }
  console.log("Guided direct-web items confirmed: " + completed + " of " + records.length +
    " in '" + albumName + "'. The script relied on your confirmation for each upload and caption.");
}

function run(argv) {
  if (argv.length === 1 && argv[0] === "--help") {
    console.log("Usage: osascript -l JavaScript scripts/share-selected-photos-to-shared-album.js --album NAME");
    console.log("       osascript -l JavaScript scripts/share-selected-photos-to-shared-album.js --dry-run --album NAME");
    console.log("       osascript -l JavaScript scripts/share-selected-photos-to-shared-album.js --album NAME --clear-album");
    console.log("       osascript -l JavaScript scripts/share-selected-photos-to-shared-album.js --dry-run --album NAME --clear-album");
    console.log("Live sharing is the default. Add --dry-run to preview without importing or sharing.");
    console.log("For --clear-album, first open the Photos Activity view. Live mode removes matching posts and their media without an extra terminal confirmation.");
    console.log("Enter a choice number in Terminal.");
    console.log("Photos posts are automated one item at a time. Direct iCloud.com is guided and requires your confirmation per item.");
    return;
  }
  try {
    main(argv);
  } catch (error) {
    console.log("ERROR: " + error.message);
    throw error;
  }
}

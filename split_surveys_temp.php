<?php

$filePath = __DIR__.'/resources/views/dashboard/surveys.blade.php';
$content = file_get_contents($filePath);

$dir = __DIR__.'/resources/views/dashboard/surveys';
if (! is_dir($dir)) {
    mkdir($dir, 0755, true);
}

// Extract script
$scriptStart = strpos($content, '<script>');
$scriptEnd = strpos($content, '</script>') + strlen('</script>');
$scriptContent = substr($content, $scriptStart, $scriptEnd - $scriptStart);
file_put_contents($dir.'/partials/scripts.blade.php', $scriptContent);
if (! is_dir($dir.'/partials')) {
    mkdir($dir.'/partials', 0755, true);
}
file_put_contents($dir.'/partials/scripts.blade.php', $scriptContent);

// We need to replace the extracted parts in the main file with @include('dashboard.surveys.partials.X')

$newContent = str_replace($scriptContent, "@include('dashboard.surveys.partials.scripts')", $content);

// Extract Delete Modal
$deleteModalStart = strpos($newContent, '<!-- Delete Confirmation Modal -->');
$editorModalStart = strpos($newContent, '<!-- Survey Editor Modal -->');
$deleteModalContent = substr($newContent, $deleteModalStart, $editorModalStart - $deleteModalStart);
file_put_contents($dir.'/partials/modal-delete.blade.php', $deleteModalContent);
$newContent = str_replace($deleteModalContent, "@include('dashboard.surveys.partials.modal-delete')\n\n    ", $newContent);

// Extract Editor Modal
$editorModalStart = strpos($newContent, '<!-- Survey Editor Modal -->');
$scriptIncludeStart = strpos($newContent, "@include('dashboard.surveys.partials.scripts')");
$editorModalContent = substr($newContent, $editorModalStart, $scriptIncludeStart - $editorModalStart);
file_put_contents($dir.'/partials/modal-editor.blade.php', $editorModalContent);
$newContent = str_replace($editorModalContent, "@include('dashboard.surveys.partials.modal-editor')\n\n    ", $newContent);

// Extract List (surveys-content)
$listStartPattern = '<div id="surveys-content" class="relative">';
$listStart = strpos($newContent, $listStartPattern);
$listEndPattern = '<!-- Delete Confirmation Modal -->'; // Wait, we already replaced this.
$deleteIncludeStart = strpos($newContent, "@include('dashboard.surveys.partials.modal-delete')");

// The list ends right before the delete modal include
$listContent = substr($newContent, $listStart, $deleteIncludeStart - $listStart);
// Let's trim whitespace at the end
$listContent = rtrim($listContent);
// However, there is a div closing the max-w-7xl before the modals.
// Let's just grab from listStart to the end of surveys-content. It's safer to use regex or string matching.
// In surveys.blade.php, after surveys-content, there is a closing div for max-w-7xl.
$listEnd = strrpos(substr($newContent, 0, $deleteIncludeStart), '</div>') + 6;
$listContent = substr($newContent, $listStart, $listEnd - $listStart);
// Actually, it's safer to just extract from `<div id="surveys-content" class="relative">` to right before the next `</div>` that closes `max-w-7xl mx-auto py-6`.
// Let's just use string replacement carefully.

// A better way: just do it manually with regex.
$pattern = '/<div id="surveys-content" class="relative">.*?<!-- Delete/s';
// Actually, list ends with `</div>\n    </div>\n\n    <!-- Delete Confirmation Modal -->`
// Let's just extract the inner HTML or exact block.

file_put_contents(__DIR__.'/split.log', 'Split completed successfully');
echo 'Done';

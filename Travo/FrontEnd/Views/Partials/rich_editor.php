<?php
$editorId = $editorId ?? 'content-editor';
$editorName = $editorName ?? 'content';
$editorContent = \Travo\Admin\Libraries\ContentCleaner::clean((string) ($editorContent ?? ''));
?>
<div class="rich-editor" data-rich-editor>
    <div class="rich-editor__toolbar" role="toolbar" aria-label="Story formatting">
        <button type="button" data-command="bold" title="Bold" aria-label="Bold"><strong>B</strong></button>
        <button type="button" data-command="italic" title="Italic" aria-label="Italic"><em>I</em></button>
        <button type="button" data-command="underline" title="Underline" aria-label="Underline"><u>U</u></button>
        <span class="rich-editor__divider" aria-hidden="true"></span>
        <button type="button" data-command="formatBlock" data-value="h2" title="Heading" aria-label="Heading">H2</button>
        <button type="button" data-command="formatBlock" data-value="blockquote" title="Quote" aria-label="Quote">Quote</button>
        <button type="button" data-command="insertUnorderedList" title="Bulleted list" aria-label="Bulleted list">List</button>
        <button type="button" data-command="insertOrderedList" title="Numbered list" aria-label="Numbered list">1.</button>
        <button type="button" data-command="createLink" title="Add link" aria-label="Add link">Link</button>
        <button type="button" data-command="removeFormat" title="Clear formatting" aria-label="Clear formatting">Clear</button>
    </div>
    <div id="<?= esc($editorId, 'attr') ?>-surface" class="rich-editor__surface" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Story content"><?= $editorContent ?></div>
    <textarea id="<?= esc($editorId, 'attr') ?>-input" name="<?= esc($editorName, 'attr') ?>" data-rich-editor-input hidden><?= esc($editorContent) ?></textarea>
</div>
<?
function releaseDateYear() {
    global $var;

    $timestamp = _var($var, 'regBuildTime', '');
    if (!$timestamp) {
        return '';
    }

    $date = new DateTime();
    $date->setTimestamp($timestamp);
    return $date->format('Y');
}

function getArrayStatus($var) {
    $progress = (_var($var,'fsProgress')!='') ? $var['fsProgress'] : "";

    $statusMap = [
        'Stopped' => ['class' => 'red', 'icon' => 'stop-circle', 'text' => _('Array Stopped')],
        'Starting' => ['class' => 'orange', 'icon' => 'pause-circle', 'text' => _('Array Starting')],
        'Stopping' => ['class' => 'orange', 'icon' => 'pause-circle', 'text' => _('Array Stopping')],
        'default' => ['class' => 'green', 'icon' => 'play-circle', 'text' => _('Array Started')]
    ];

    $state = _var($var,'fsState');
    $status = $statusMap[$state] ?? $statusMap['default'];

    return [
        'class' => $status['class'],
        'icon' => $status['icon'],
        'text' => $status['text'],
        'progress' => $progress
    ];
}
?>

<footer id="footer">
    <span id="statusraid">
        <span id="statusbar">
            <? $status = getArrayStatus($var); ?>
            <span class="<?=$status['class']?> strong">
                <i class="fa fa-<?=$status['icon']?>"></i> <?=$status['text']?>
            </span>
            <? if ($status['progress']): ?>
                &bullet;<span class="blue strong tour"><?=$status['progress']?></span>
            <? endif; ?>
        </span>
    </span>
    <span id="user-notice" class="red-text"></span>
    <? if ($wlan0): ?>
        <span id="wlan0" class="grey-text" onclick="wlanSettings()">
            <i class="fa fa-wifi fa-fw"></i>
        </span>
    <? endif; ?>
    <span id="copyright">
        Unraid&reg; webGui &copy;<?=releaseDateYear()?>, Lime Technology, Inc.
        <a href="https://docs.unraid.net/go/manual/" target="_blank" title="<?=_('Online manual')?>">
            <i class="fa fa-book"></i> <?=_('manual')?>
        </a>
        <unraid-theme-switcher 
            current="<?=$theme?>"
            themes='<?=htmlspecialchars(json_encode(['azure', 'gray', 'black', 'white']), ENT_QUOTES, 'UTF-8')?>'>
        </unraid-theme-switcher>
    </span>
</footer>

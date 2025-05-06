<div id="header" class="<?=$display['banner']?>">
    <div class="logo">
        <a href="https://unraid.net" target="_blank">
            <?readfile("$docroot/webGui/images/UN-logotype-gradient.svg")?>
        </a>

        <unraid-i18n-host>
            <unraid-header-os-version></unraid-header-os-version>
        </unraid-i18n-host>
    </div>

    <?include "$docroot/plugins/dynamix.my.servers/include/myservers2.php"?>
</div>

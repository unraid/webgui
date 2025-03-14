<style>
    #patcher-version-container {
        text-align: start !important;
        align-items: center;
        display: flex;
    }

    .patcher-version-badge {
        display: inline-flex;
        line-height: 1;
        font-weight: 700;
        font-size: 14px;
        padding: 0.25rem 0.5rem;
        color: var(--header-text-secondary, #8c8c8c);
        transition: color 0.2s;
    }

    .patcher-version-badge svg {
        width: 24px !important;
        margin-top: 0px !important;
        margin-bottom: 0px !important;
    }

    .patcher-version-badge:hover {
        text-decoration: underline !important;
    }

    .patcher-update-badge {
        background-color: unset !important;
        background: linear-gradient(90deg, #e22828 0, #ff8c2f) 0 0 no-repeat, linear-gradient(90deg, #e22828 0, #ff8c2f) 0 100% no-repeat, linear-gradient(0deg, #e22828 0, #e22828) 0 100% no-repeat, linear-gradient(0deg, #ff8c2f 0, #ff8c2f) 100% 100% no-repeat;
        display: inline-flex;
        align-items: center;
        line-height: 1;
        border-radius: 1rem;
        padding: 0.5rem 0.75rem;
        color: white;
        margin-left: 4px;
        letter-spacing: 0.5px;
        text-transform: none;
    }

    /* Hide the original unraid-header-os-version element */
    unraid-header-os-version {
        display: none;
    }
</style>

<script>
    // Function to wait for an element to appear in the DOM
    function waitForElement(selector, callback, maxWaitTime = 10000) {
        if (document.querySelector(selector)) {
            callback();
            return;
        }

        let waited = 0;
        const interval = 100;
        const checkInterval = setInterval(function() {
            waited += interval;
            if (document.querySelector(selector)) {
                clearInterval(checkInterval);
                callback();
            } else if (waited >= maxWaitTime) {
                clearInterval(checkInterval);
                console.error(`Element ${selector} not found after ${maxWaitTime}ms`);
            }
        }, interval);
    }

    // Function to inject our version display handler
    function injectVersionHandler() {
        // Wait for the unraid-header-os-version element before proceeding
        waitForElement('unraid-header-os-version', function() {
            // Function to handle the version display
            function updateVersionDisplay() {
                // Find the update-os web component
                const updateOsElement = document.querySelector('unraid-header-os-version');
                if (!updateOsElement) return;

                // No longer directly hiding the element here
                // The CSS rule above will handle hiding it

                // Create our custom version display
                const versionContainer = document.createElement('div');
                versionContainer.className = 'flex flex-row justify-start items-center gap-x-4px';
                versionContainer.id = 'patcher-version-container';

                // Get version information
                <?php
                $patchInfo = [];
                if (file_exists('/tmp/Patcher/patches.json')) {
                    $patchInfo = @json_decode(@file_get_contents('/tmp/Patcher/patches.json'), true) ?: [];
                }
                $originalVersion = htmlspecialchars($var['version'] ?? '');
                $patchedVersion = htmlspecialchars($patchInfo['displayVersion'] ?? '');
                ?>

                let originalVersion = '<?= $originalVersion ?>';
                let patchedVersion = '<?= $patchedVersion ?>';
                let changelogUrl = `https://docs.unraid.net/unraid-os/release-notes/${originalVersion.split('-')[0]}`;

                // Create version badge with changelog link
                const versionBadge = document.createElement('a');
                versionBadge.className = 'patcher-version-badge group leading-none';
                versionBadge.title = 'View release notes';
                versionBadge.href = changelogUrl;
                versionBadge.target = '_blank';
                versionBadge.rel = 'noopener';
                versionBadge.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" data-slot="icon" class="flex-shrink-0 w-14px text-gamma" width="14" height="14"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm8.706-1.442c1.146-.573 2.437.463 2.126 1.706l-.709 2.836.042-.02a.75.75 0 0 1 .67 1.34l-.04.022c-1.147.573-2.438-.463-2.127-1.706l.71-2.836-.042.02a.75.75 0 1 1-.671-1.34l.041-.022ZM12 9a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd"></path></svg>${patchedVersion || originalVersion}`;

                // Add to container
                versionContainer.appendChild(versionBadge);

                // Check if there's an update available by looking at the original element
                try {
                    // Try to access shadow DOM if browser supports it
                    if (updateOsElement.shadowRoot) {
                        const updateBadge = updateOsElement.shadowRoot.querySelector('button[title]');

                        if (updateBadge) {
                            const badgeText = updateBadge.textContent.trim();
                            const isUpdateAvailable = badgeText.includes('Update');

                            if (isUpdateAvailable) {
                                const updateLink = document.createElement(updateBadge.tagName);
                                updateLink.className = 'patcher-update-badge'

                                if (updateBadge.tagName.toLowerCase() === 'a') {
                                    updateLink.href = updateBadge.getAttribute('href');
                                } else {
                                    updateLink.addEventListener('click', () => {
                                        // Trigger the original button's click handler
                                        updateBadge.click();
                                    });
                                }

                                updateLink.textContent = badgeText;
                                updateLink.title = updateBadge.title || badgeText;
                                versionContainer.appendChild(updateLink);
                            }
                        }
                    }
                } catch (e) {
                    console.error('Error accessing update-os component:', e);
                }

                // Insert our custom display
                updateOsElement.parentNode.insertBefore(versionContainer, updateOsElement.nextSibling);
            }

            // Initial update
            updateVersionDisplay();
        });
    }

    // Add the script to the page
    injectVersionHandler();
</script>
# Unraid webgui repo

## Structure

The emhttp, etc, sbin, and src dirs in this repo are extracted to the /usr/local/ directory when an Unraid release is built.

## Contributions

Please be aware that there is a fairly high barrier to entry when working on the Unraid webgui. It has grown organically over the years and you will need to do quite a bit of reverse engineering to understand it.

If you choose to proceed, clone this repo locally, test edits by copying the changes to your server, then submit a PR to <https://github.com/unraid/webgui> when fully tested.

Be sure to describe (in the Unraid forum, a GitHub issue, or the GitHub PR) the problem/feature you are working on and explain how the proposed change solves it.

We recommend using VS Code with the following plugins:

* Install [natizyskunk.sftp](https://marketplace.visualstudio.com/items?itemName=Natizyskunk.sftp) and use .vscode/sftp-template.json as a template for creating your personalized sftp.json file to have VS Code upload files to your server when they are saved. Your personalized sftp.json file is blocked by .gitignore so it should never be included in your PR.
* Install [bmewburn.vscode-intelephense-client](https://marketplace.visualstudio.com/items?itemName=bmewburn.vscode-intelephense-client) and when reasonable, implement its recommendations for PHP code.
* Install [DavidAnson.vscode-markdownlint](https://marketplace.visualstudio.com/items?itemName=DavidAnson.vscode-markdownlint) and when reasonable, implement its recommendations for markdown code.
* Install [foxundermoon.shell-format](https://marketplace.visualstudio.com/items?itemName=foxundermoon.shell-format) and [timonwong.shellcheck](https://marketplace.visualstudio.com/items?itemName=timonwong.shellcheck) and when reasonable, implement their recommendations when making changes to bash scripts.
* Install [esbenp.prettier-vscode](https://marketplace.visualstudio.com/items?itemName=esbenp.prettier-vscode) and when reasonable, use it for formatting files.

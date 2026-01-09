# lang-zh_CN

### 请在您准备翻译前阅读以下内容!

这个翻译项目里包含了16个txt文件, 分别对应Unraid系统中相应的部分:

- translations.txt -- 包含通用(general)翻译, 每次都会载入
- helptext.txt     -- 包含所有帮助文本的翻译 , 每次都会载入
- dashboard.txt    -- 包含仪表盘(dashboard)部分的翻译
- main.txt         -- 包含主要部分的翻译
- shares.txt       -- 包含共享部分的翻译
- users.txt        -- 包含用户部分的翻译
- settings.txt     -- 包含设置部分的翻译
- plugins.txt      -- 包含插件部分的翻译
- docker.txt       -- 包含容器(docker)部分的翻译
- vms.txt          -- 包含虚拟机部分的翻译
- tools.txt        -- 包含工具部分的翻译
- javascript.txt   -- 包含javascript脚本部分的翻译
- scripts.txt      -- 包含本地脚本部分的翻译
- apps.txt         -- 包含社区软件(apps)部分的翻译
- ca_settings      -- 包含社区软件设置部分的翻译
- javascript.ca.txt-- 社区软件提供的javascript部分的翻译

为保证翻译完整性, 所有文件名必须是小写的, 并且所有文件都应该包含在仓库中.

删除掉某个文件，意味着该部分将没有可用的翻译，WEB GUI将显示原始的英语文本.

### 翻译

每个文本文件都包含以UTF-8格式存储的常规文本字符串, 并带有linux行结束符.
应使用支持UTF-8和linux格式的文本编辑器, 例如 [notepad++](https://notepad-plus-plus.org/downloads)

每个文本文件的内容分为两个部分

### 第一部分

具有固定格式的单行条目:

`原始的英语文本=翻译后的文本`

只修改等号(=)后面的文本,不应修改左侧的原始英文文本.
删除一行或省略等号后面的翻译, WEB GUI将会显示这一行内容的原始英语文本.

翻译后的文本可含有"特殊字符", 如斜杠, 圆括号或方括号, 这些字符原本不在原文中, 不过可被添加以按需补充说明性文字，使译文易于理解. 
例如.
`Options see Help=选项 (查看帮助)`

使用 \* 字符标示内容, 可使得对应内容以斜体显示,
使用 \*\* 字符标示内容, 可使得对应内容以粗体显示.
例如.
`Array must be Stopped to change=如要更改设置, 磁盘阵列必须先**停止**`

建议分别翻译各个部分, 即每次翻译一个文件, 并在WEB GUI中验证翻译的正确性.

请尽可能匹配翻译后文本与原文本的长度, 避免WEB GUI中显示错乱.

### 第二部分

用于一次性翻译多行文本的条目.
多行翻译条目有唯一的开始和结束标签:

**:unique_tag_name_plug:** - 用于任何多行文本(unique_tag_name_plug)部分的唯一开始标签

**:end**    - 多行翻译条目的结束标签

不要删除或改变这些标签, 只翻译开始和结束标签之间的文本!

### 帮助文本

WEB GUI所有的帮助文本都存储在一个文件中 *helptext.txt*.

这个文件有包含多个部分的帮助文本. 每个部分都包含唯一的开始标签和相应的结束标签.

**:unique_tag_name_help:** - 帮助文本部分的唯一开始标签

**:end**    - 相应的结束标签

不要删除或改变这些标签, 只翻译开始和结束标签之间的文本!

注意: 使用了Markdown语法, 相关的格式描述符必须保留.

### 本地测试

翻译完成后，如您希望在本地(临时)测试结果, 需要将文本文件压缩到单个ZIP文件中.
将ZIP文件命改为您翻译的语言, 例如. zh_CN.zip.

在WEB GUI中如下设置: 工具 -> webGUI -> 语言 (切换到开发者视图)

- 默认情况下, 只安装英语语言(内置).
- 选择翻译后创建的ZIP文件.
- 如果系统识别出语言名称, 将自动选择语言名称, 否则请在下拉菜单中选择语言名称进行安装.
- 点击"上传", 您的翻译会出现在WEB GUI语言选项的下拉菜单中.

提示: 如果下拉菜单中没有列出您的语言, 请在论坛中提交反馈 [Unraid forum](https://forums.unraid.net/forum/75-multi-language-section/)

现在你可以对翻译后的文本进行本地测试了!

在WEB GUI中如下设置: Settings -> Display Settings -> Language

- 从下拉菜单中选择首选语言. (只有成功安装语言才会出现在列表中)

### GITHUB

语言仓库在 [Github](https://github.com/unraid) 上提供, 翻译人员可以随时创建PR(Pull Request)提交翻译成果.

请使用Github(需要注册帐户)派生(fork)官方语言存储库到自己的账户, 一旦您翻译完成, 就可以创建PR.

负责人 Limetech 将审查提交内容, 审查通过后会合并您的修改.

### 更新

当有新的英语文本可用时, 会在Github提供更新.

翻译人员可以使用Github系统查看做出了哪些更改, 并相应地更新他们的翻译.

### 致谢

我们非常欢迎您参与到翻译工作中，为了表达我们的感激之情，您的名字和语言将被记录到GUI工具页面下的"致谢"(Credits)页面。

非常感谢!


# lang-zh_CN

### READ THIS WHEN YOU WANT TO MAKE TRANSLATIONS TO ANOTHER LANGUAGE

There are sixteen text files included in this repository, each with their own section of translations:

- translations.txt -- these are general translations and loaded each time
- helptext.txt     -- these are all help text sections and loaded each time
- dashboard.txt    -- these are translations for the dashboard section
- main.txt         -- these are translations for the main section
- shares.txt       -- these are translations for the shares section
- users.txt        -- these are translations for the users section
- settings.txt     -- these are translations for the settings section
- plugins.txt      -- these are translations for the plugins section
- docker.txt       -- these are translations for the docker section
- vms.txt          -- these are translations for the vms section
- tools.txt        -- these are translations for the tools section
- javascript.txt   -- these are translations for javascript scripts
- scripts.txt      -- these are translations for local scripts
- apps.txt         -- these are translations for the CA section
- ca_settings      -- these are translations for the CA settings
- javascript.ca.txt-- these are translations for the CA javascript

All file names are in lowercase and should be included in the repository to make the translations complete.

Removing a particular file, means no translations will be available for that section and the GUI will display text in original English.

### TRANSLATIONS

Each text file contains regular text strings stored in UTF-8 format with linux line-endings.
Use a text editor which supports UTF-8 and linux format, like [notepad++](https://notepad-plus-plus.org/downloads)

The content of each text file is separated into two parts

### PART 1

These are single line entries which are in the format:

`original English text=translated Foreign text`

Only modify the text after the equal sign(=) and leave the original English text at the left untouched.
Removing a line or omitting a translation after the equal sign, results in the GUI displaying this line with the original English text.

The translated text may have 'special characters', such as slashes, parenthesis or brackets which are not included in the key text,
but which are used to display text accordingly. E.g.

`Options see Help=Options (see Help)`

The characters \* and \*\* are used to display text in italics and bold respectively. E.g.

`Array must be Stopped to change=Array must be **Stopped** to change`

It is recommended to make translations per section, that is one file at the time, and verify the correctness of the translations in the GUI
before proceeding with the next section.

Keep in mind the length of the translations and try to make them similar length as the original text and avoid space issues in the GUI.

### PART 2

These are multi line entries used to translate multiple lines at once.
Multi line translations have a unique opening and closing tag:

**:unique_tag_name_plug:** - unique opening tag used for any multi line text section

**:end**    - closing tag

Do not remove or alter these tags and only translate the text between the opening and closing tags!

### HELP TEXT

All help text of the GUI is stored in a single file *helptext.txt*.

This file has multiple help text sections. Each section is enclosed by a unique opening tag and corresponding closing tag.

**:unique_tag_name_help:** - unique opening tag used for a help text section

**:end**    - corresponding closing tag

Do not remove or alter these tags and only translate the text between the opening and closing tags!

Be aware that Markdown styling syntax is used, this must be preserved.

### LOCAL TESTING

Once the translations are complete and you want to test locally the (intermediate) results, the text files need to be zipped into a single ZIP file.
Give the ZIP file the name of your language, e.g. French.zip.

In the GUI go to: Tools -> webGUI -> Language (switch to Developer view)

- By default only the English language is installed (built-in)
- Choose the ZIP file you have created earlier as the source file
- If the language name is recognized, it will be automatically selected, otherwise chose the name of the language from the dropdown menu to install.
- Click on "Upload" will add your translations to the GUI under the selected language name

NOTE: If your language is not available from the dropdown menu, please make a request on the [Unraid forum](https://forums.unraid.net/forum/75-multi-language-section/)

Now your language is available for local testing!

In the GUI go to: Settings -> Display Settings -> Language

- Select the preferred language from the dropdown menu. Note that only the available language choices are displayed here.

### GITHUB

A language repository will be made available at [Github](https://github.com/unraid), where translators can create Pull Requests (PR) and submit their work.

Once you are satisfied with your results, use Github (an account is required) to fork the respective language repository and create a PR with your modifications.

Limetech will review this and merge your work when approved.

### UPDATES

When updated source text files in English become available in the future, these updates will be made available through Github.

Translators can use the Github system to see which changes are made and update their translations accordingly.

### CREDITS

Your efforts are much welcomed and to show our appreciation, your name and language are credited on the Credits page under Tools in the GUI.
Please let us know which credentials to use.

Thank you very much!

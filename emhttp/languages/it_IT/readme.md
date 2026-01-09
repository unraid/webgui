# lang-it_IT

### SI PREGA DI PRENDERE VISIONE DI QUANTO SEGUE SE SI DESIDERA CONTRIBUIRE ALLA TRADUZIONE IN LINGUA ITALIANA

In questo repository sono inclusi diversi file di testo, ciascuno con la propria sezione di traduzioni:

<pre>
- <strong>translations.txt</strong>     -- traduzioni <strong>generali</strong>, utilizzate in diverse sezioni
- <strong>helptext.txt</strong>         -- traduzioni per le sezioni di <strong>aiuto</strong>
- <strong>dashboard.txt</strong>        -- traduzioni per la sezione <strong>Dashboard</strong>
- <strong>main.txt</strong>             -- traduzioni per la sezione <strong>Principale</strong>
- <strong>shares.txt</strong>           -- traduzioni per la sezione <strong>Condivisioni</strong>
- <strong>users.txt</strong>            -- traduzioni per la sezione <strong>Utenti</strong>
- <strong>settings.txt</strong>         -- traduzioni per la sezione <strong>Impostazioni</strong>
- <strong>plugins.txt</strong>          -- traduzioni per la sezione <strong>Plugin</strong>
- <strong>docker.txt</strong>           -- traduzioni per la sezione <strong>Docker</strong>
- <strong>vms.txt</strong>              -- traduzioni per la sezione <strong>Macchine virtuali</strong>
- <strong>tools.txt</strong>            -- traduzioni per la sezione <strong>Strumenti</strong>
- <strong>javascript.txt</strong>       -- traduzioni per gli script <strong>JavaScript</strong>
- <strong>scripts.txt</strong>          -- traduzioni per gli <strong>Script locali</strong>
- <strong>apps.txt</strong>             -- traduzioni per la sezione <strong>App</strong> <em>(CA = Community Applications)</em>
- <strong>ca_settings.txt</strong>      -- traduzioni per le <strong>Impostazioni App</strong> <em>(CA)</em>
- <strong>javascript.ca.txt</strong>    -- traduzioni per gli <strong>Script JavaScript</strong> della sezione <em>CA</em>
</pre>

Tutti i nomi dei file sono in minuscolo e <strong>devono essere inclusi nel repository</strong> per rendere complete le traduzioni.

La rimozione di un determinato file comporta l’assenza di traduzioni per quella sezione e l’interfaccia grafica *(GUI)* mostrerà il testo in inglese originale.


### TRADUZIONI

Ogni file di testo contiene stringhe di testo regolare, memorizzate in formato UTF-8 con terminazioni di riga in formato Linux.
Utilizzare un editor di testo che supporti UTF-8 e il formato Linux, come [Notepad++](https://notepad-plus-plus.org/downloads).

Il contenuto di ciascun file di testo è suddiviso in due parti, come illustrato di seguito.


### PARTE 1

Queste sono voci su singola riga visualizzate nel seguente formato:

<pre>Testo originale in inglese=Traduzione nella lingua desiderata</pre>

Modificare solo il testo dopo il segno di uguale **(=)** e lasciare invariato il testo originale in inglese a sinistra.
La rimozione di una riga o l’omissione della traduzione dopo il segno di uguale comporterà la visualizzazione, nella *GUI*, del testo originale in inglese per quella riga.

Il testo tradotto può contenere “caratteri speciali” come *barre*, *parentesi tonde* o *parentesi quadre*, che non sono inclusi nel testo chiave, ma servono per la corretta visualizzazione del testo.
Ad esempio:

<pre>Options see Help=Opzioni (vedi Aiuto)</pre>

I caratteri \* e \*\* sono utilizzati per visualizzare rispettivamente il testo in *corsivo* e in **grassetto**.
Ad esempio:

"Array must be Stopped to change=L’*Array* deve essere **Fermato** per apportare le modifiche".

Si consiglia di effettuare le traduzioni per sezione, ossia un file alla volta, e di verificarne la correttezza nella *GUI* prima di procedere con la sezione successiva.

Tenere presente la lunghezza delle traduzioni, cercando di mantenerla simile a quella del testo originale, così da evitare problemi di spaziatura nella *GUI*.


### PARTE 2

Queste sono voci su più righe utilizzate per tradurre più righe contemporaneamente.
Le traduzioni su più righe hanno un tag di apertura e chiusura univoco:

**:unique_tag_name_plug:** – tag di apertura univoco utilizzato per qualsiasi sezione di testo 

su più righe

**:end**    - tag di chiusura

⚠️ Non rimuovere o modificare questi tag; tradurre **solo** il testo compreso tra il tag di apertura e quello di chiusura!


### TESTO DI AIUTO

Tutto il testo di aiuto della *GUI* è memorizzato in un singolo file denominato **helptext.txt**.

Questo file contiene più sezioni di testo di aiuto.
Ogni sezione è racchiusa da un tag di apertura univoco e dal corrispondente tag di chiusura:

**:unique_tag_name_help:**	– tag di apertura univoco utilizzato per una sezione di testo di aiuto

**:end**	– tag di chiusura corrispondente

⚠️ Non rimuovere o modificare questi tag; tradurre **solo** il testo compreso tra il tag di apertura e quello di chiusura!

Nota: viene utilizzata la sintassi di stile *Markdown*, la quale deve essere preservata.


### TEST LOCALI

Una volta completate le traduzioni, se si desidera testare localmente i risultati (anche parziali), i file di testo devono essere **compressi in un unico file ZIP**.

Dare al file ZIP il nome della propria lingua, ad esempio: "**Italian.zip**".

Nella *GUI* di Unraid andare in: **Strumenti → Interfaccia Grafica Web → Lingua** (*e passare alla vista Sviluppatore*)

- Per impostazione predefinita, è inclusa solo la lingua inglese (integrata nativamente).
- Selezionare come file sorgente il file ZIP creato in precedenza.
- Se il nome della lingua viene riconosciuto, verrà selezionato automaticamente; altrimenti, scegliere il nome della lingua dal menu a discesa per installarla.
- Fare clic su 'Carica' per aggiungere la traduzione alla *GUI* sotto il nome lingua selezionato.

NOTA: Se la lingua non è disponibile nel menu a discesa, fare richiesta nella [Sezione Multi-Lingua del Forum di Unraid](https://forums.unraid.net/forum/75-multi-language-section/).

A questo punto la lingua sarà disponibile per i test locali!

Nella *GUI* di Unraid andare in: **Impostazioni → Impostazioni Interfaccia → Lingua**

- Selezionare la lingua preferita dal menu a discesa. Si ricorda che qui vengono visualizzate solo le lingue disponibili.


### GITHUB

Un *language repository* sarà reso disponibile su [GitHub](https://github.com/unraid), dove i traduttori potranno creare delle *Pull Request (PR)* per inviare il proprio lavoro.

Una volta soddisfatti del risultato, utilizzare *GitHub* (è necessario un account) per effettuare il *fork* del repository della lingua corrispondente e creare una *PR* per inoltrare le proprie modifiche.

**LimeTech** esaminerà la proposta e, se approvata, integrerà il tuo lavoro.


### AGGIORNAMENTI

Quando in futuro saranno disponibili versioni aggiornate dei file sorgente in inglese, queste saranno pubblicate tramite *GitHub*.

I traduttori potranno usare il sistema *GitHub* per verificare le modifiche apportate e aggiornare di conseguenza le proprie traduzioni.


### CREDITI

Il tuo contributo è molto apprezzato e, come segno di riconoscenza, il tuo nome e la lingua tradotta saranno inseriti nella sezione **Crediti** della pagina **Strumenti** nella *GUI di Unraid*.

Ti preghiamo di indicarci quale nome e credenziali utilizzare.

Grazie mille per la collaborazione e l’impegno ❤️

document.addEventListener('DOMContentLoaded', (event) => {
    // --------------------------------------------------
    function setCookie(cname, cvalue, exdays) {
        var d = new Date();
        d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
        var expires = "expires=" + d.toUTCString();
        document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
    }
    // --------------------------------------------------
    function getCookie(cname) {
        var name = cname + "=";
        var decodedCookie = decodeURIComponent(document.cookie);
        var ca = decodedCookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) == ' ') {
                c = c.substring(1);
            }
            if (c.indexOf(name) == 0) {
                return c.substring(name.length, c.length);
            }
        }
        return "";
    }
    // --------------------------------------------------
    var today = new Date();
    var sToday = today.toISOString().split('T')[0];
    // --------------------------------------------------
    if (typeof(Storage) == "undefined") {
        setCookie("fileList", '', 30);
    } else {
        localStorage.setItem('fileList', '');
    }
    // --------------------------------------------------
    ele00 = document.getElementById('personia_contab_data');
    if (ele00) {
        ele00.value = sToday;
        ele00.max = sToday;
    }
    // --------------------------------------------------
    ele01 = document.getElementById('personia_contab_add_piano_01');
    if (ele01) {
        ele01.addEventListener('click', function() {
            var selectElement = document.getElementById('personia_contab_codice');
            selectElement.value = 56;
        });
    }
    // --------------------------------------------------
    ele02 = document.getElementById('personia_contab_add_piano_02');
    if (ele02) {
        ele02.addEventListener('click', function() {
            var selectElement = document.getElementById('personia_contab_codice');
            selectElement.value = 21;
        });
    }
    // --------------------------------------------------
    ele03 = document.getElementById('personia_contab_add_piano_03');
    if (ele03) {
        ele03.addEventListener('click', function() {
            var selectElement = document.getElementById('personia_contab_codice');
            selectElement.value = 441;
        });
    }
    // --------------------------------------------------
    ele04 = document.getElementById('personia_contab_add_piano_04');
    if (ele04) {
        ele04.addEventListener('click', function() {
            var selectElement = document.getElementById('personia_contab_codice');
            selectElement.value = 448;
        });
    }
    // --------------------------------------------------
    ele05 = document.getElementById('personia_contab_add_piano_05');
    if (ele05) {
        ele05.addEventListener('click', function() {
            var selectElement = document.getElementById('personia_contab_codice');
            selectElement.value = 204;
        });
    }
    // --------------------------------------------------
    ele06 = document.getElementById('personia_contab_add_piano_06');
    if (ele06) {
        ele06.addEventListener('click', function() {
            var selectElement = document.getElementById('personia_contab_codice');
            selectElement.value = 155;
        });
    }
    // --------------------------------------------------
    ele07 = document.getElementById('personia_contab_add_piano_07');
    if (ele07) {
        ele07.addEventListener('click', function() {
            var selectElement = document.getElementById('personia_contab_codice');
            selectElement.value = 160;
        });
    }
    // --------------------------------------------------
    ele08 = document.getElementById('personia_contab_add_piano_08');
    if (ele08) {
        ele08.addEventListener('click', function() {
            var selectElement = document.getElementById('personia_contab_codice');
            selectElement.value = 536;
        });
    }
    // --------------------------------------------------
    const BuExport = document.querySelector('.bu_contab_personia_download');
    if (BuExport) {
        BuExport.addEventListener('click', function() {
            var filePath = this.value;
            var fileName = this.name;
            var downloadLink = document.createElement("a");
            downloadLink.href = filePath;
            downloadLink.download = fileName;
            downloadLink.target = "_blank";
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        });
    }
    // --------------------------------------------------
    const buRap = document.querySelector('.bu_contab_personia_rap');
    if (buRap) {
        buRap.addEventListener('click', function() {
            let pText = this.previousElementSibling;
            const contentToPrint = pText.innerHTML;
            var printWindow = window.open('', '_blank');
            if (printWindow) {
                printWindow.document.open();
                printWindow.document.write(contentToPrint);
                printWindow.document.close();
                printWindow.print();
            }
        });
    }
    // --------------------------------------------------
    const BuAfterTable = document.querySelector('.bu_contab_personia_tab');
    if (BuAfterTable) {
        BuAfterTable.addEventListener('click', function() {
            var title = this.title;
            var table = this.previousElementSibling;
            var htmlTable = table.outerHTML
            var printWindow = window.open('', '_blank');
            if (printWindow) {
                printWindow.document.open();
                printWindow.document.write(`
                <html>
                <head>
                  <title>Stampa la tabella</title>
                  <style>
                    table {
                      border-collapse: collapse;
                      width: 100%;
                      font-family: Arial, sans-serif;
                    }
                    th, td {
                      padding: 8px;
                      text-align: left;
                      border-bottom: 1px solid #ddd;
                    }
                    th {
                      background-color:LightGreen;
                    }
                    tr:nth-child(odd) {
                        background-color:Azure;
                    }
                    tr:nth-child(even) {
                        background-color:LightGreen;
                    }
                  </style>
                </head>
                <body>
                    <h1>
                        ${title}
                    </h1>
                    <table>
                        ${htmlTable}
                    </table>
                </body>
                </html>
                `);
                printWindow.document.close();
                printWindow.print();
            }
        });
    }
    // --------------------------------------------------
    const fileInput = document.getElementById('personia_contab_fileInput');
    const uploadMessage = document.getElementById("upload_message");
    if (fileInput) {
        var progressBar = document.getElementById('personia_contab_progressBar');
        fileInput.addEventListener('change', function() {
            var files = fileInput.files;
            var formData = new FormData();
            for (var i = 0; i < files.length; i++) {
                formData.append('files[]', files[i]);
            }
            formData.append('action', 'personia_contab_file_upload');
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajax_object.ajax_url);
            xhr.upload.onprogress = function(event) {
                if (event.lengthComputable) {
                    var percentComplete = (event.loaded / event.total) * 100;
                    progressBar.style.width = percentComplete + '%';
                }
            };
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    var nErr = response.data.errors.length>0?response.data.errors.length:0;
                    var nOk = response.data.uploaded.length>0?response.data.uploaded.length:0;
                    var mErr = (nErr>0)?response.data.errors:[] ;
                    var mOk = [];
                    var mReg = [];
                    var mLinks = []; 
                    if (nOk>0) {
                        mOk = response.data.uploaded
                        mReg = response.data.registered;
                        mLinks = mReg.map((url,i) => `<a href="${url}" target="_blank">url${i+1}</a>`);
                    }
                    var pubMessage = 'Errori: ' + nErr + '<br />Caricati: ' + nOk + 
                        '<hr /><b>Errori</b><br />' + mErr.join('<br />') + 
                        '<hr /><b>Caricati</b><br />' + mOk.join('<br />') +
                        '<hr /><b>Registrati</b><br />' + mLinks.join(', ') ; 
                    uploadMessage.innerHTML = pubMessage;
                    var fileListJson = JSON.stringify(mReg);
                    if (typeof(Storage) == "undefined") {
                        setCookie("fileList", fileListJson, 30);
                    } else {
                        localStorage.setItem('fileList', fileListJson);
                    }
                } else {
                    uploadMessage.innerHTML = 'Errore di caricamento file.<br />xhr.status = ' + xhr.status;
                }
            };
            xhr.send(formData);
        });
    }
    // --------------------------------------------------
    function populateSelect(selectElement, objectList) {
        for (const obj of objectList) {
            const option = document.createElement("option");
            option.text = obj.text;
            option.value = obj.value;
            selectElement.appendChild(option);
        }
    }
    // --------------------------------------------------
    function createImageGallery(list) {
        let overlay = document.createElement('div');
        overlay.style.position = 'fixed';
        overlay.style.top = '0';
        overlay.style.left = '0';
        overlay.style.width = '100%';
        overlay.style.height = '100%';
        overlay.style.backgroundColor = 'rgba(0, 0, 0, 0.7)';
        overlay.style.display = 'flex';
        overlay.style.alignItems = 'center';
        overlay.style.justifyContent = 'center';
        overlay.style.zIndex = '9999';
        let gallery = document.createElement('div');
        gallery.style.display = 'flex';
        gallery.style.overflowX = 'auto';
        list.forEach(item => {
            let imgSrc = item.match(/<img src="([^"]+)"/)[1];
            let linkHref = item.match(/href="([^"]+)"/)[1];
            let img = document.createElement('img');
            img.src = imgSrc;
            img.style.maxWidth = '100%';
            img.style.maxHeight = '100%';
            img.style.objectFit = 'contain';
            img.style.margin = '5px';
            img.onclick = function() {
                closeGallery(linkHref);
            };
            gallery.appendChild(img);
        });
        overlay.appendChild(gallery);
        document.body.appendChild(overlay);
        function closeGallery(linkHref) {
            document.body.removeChild(overlay);
            console.log(linkHref);
        }
    }
    // --------------------------------------------------
    copiaAllegato = document.getElementById('personia_contab_copia_allegato');
    if (copiaAllegato) {
        copiaAllegato.addEventListener('click', function() {
            var formData = new FormData();
            formData.append('action', 'personia_contab_list_allegati');
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajax_object.ajax_url);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var links = JSON.parse(xhr.responseText);
                    console.log(links);
                } else {
                    console.log( 'Non riesco a compilare la lista degli allegati. Status: ' + xhr.status + "\n" + 'xhr = ' + JSON.stringify(xhr, null, 2) );
                }
            };
            xhr.onerror = function() {
                console.log('Request Error:', xhr.status, xhr.statusText);
            };
            xhr.send(formData);
        });
    }
    // --------------------------------------------------
    submitMovimento = document.getElementById('submit_movimento');
    if (submitMovimento) {
        submitMovimento.addEventListener('click', function() {
            var storedFileListJson;
            if (typeof(Storage) == "undefined") {
                storedFileListJson = getCookie('fileList');
            } else {
                storedFileListJson = localStorage.getItem('fileList');
            }
            var storedFileList = JSON.parse(storedFileListJson);
            var hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'storedFileList';
            hiddenInput.value = JSON.stringify(storedFileList);
            var form = document.querySelector('#personia_contab_form_movimento');
            form.appendChild(hiddenInput);
            form.submit();
        });
    }
    // --------------------------------------------------
});

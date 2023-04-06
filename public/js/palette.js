'use strict';

let previewFile;
let initialized = false;

function applyPalette(parsed)
{
    const palettes = parsed.properties.palettes;
    let index = 0;
    for (let j = 0; index < 236; j++, index++)
    {
        const color = palettes.general.colours[j];
        document.getElementById('palette-color-' + index).value = color;
    }
    for (let j = 0; index < 251; j++, index++)
    {
        const color = palettes["waves-0"].colours[j];
        document.getElementById('palette-color-' + index).value = color;
    }
    for (let j = 0; index < 266; j++, index++)
    {
        const color = palettes["waves-1"].colours[j];
        document.getElementById('palette-color-' + index).value = color;
    }
    for (let j = 0; index < 281; j++, index++)
    {
        const color = palettes["waves-2"].colours[j];
        document.getElementById('palette-color-' + index).value = color;
    }
    for (let j = 0; index < 296; j++, index++)
    {
        const color = palettes["sparkles-0"].colours[j];
        document.getElementById('palette-color-' + index).value = color;
    }
    for (let j = 0; index < 311; j++, index++)
    {
        const color = palettes["sparkles-1"].colours[j];
        document.getElementById('palette-color-' + index).value = color;
    }
    for (let j = 0; index < 326; j++, index++)
    {
        const color = palettes["sparkles-2"].colours[j];
        document.getElementById('palette-color-' + index).value = color;
    }
}

async function updatePreview()
{
    let ownFile = document.querySelector('#own-image-preview-file');
    let response;
    if (ownFile.files.length > 0)
    {
        let data = new FormData(document.querySelector('#palette-form'));
        data.append('object', ownFile.files[0]);

        response = await fetch('/palette/preview-own', {
            method: 'POST',
            body: data
        });
    }
    else
    {
        response = await fetch('/palette/preview', {
            method: 'POST',
            body: new FormData(document.querySelector('#palette-form'))
        });
    }

    response.blob().then((file) => {
        previewFile = file;
        previewFile.name = 'preview.png';
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onloadend = () => {
            document.querySelector('#palette-preview-img').src = reader.result;
        }
    });
}

function maximizePreview()
{
    document.getElementById('palette-left').style.display = 'none';
    document.getElementById('palette-right').style.width = 'auto';
    document.getElementById('palette-preview-maximize').style.display = 'none';
    document.getElementById('palette-preview-restore').style.display = 'flex';
}

function restorePreview()
{
    document.getElementById('palette-left').style.display = 'block';
    document.getElementById('palette-right').style.width = '';
    document.getElementById('palette-preview-maximize').style.display = 'flex';
    document.getElementById('palette-preview-restore').style.display = 'none';
}

function afterLoad(text)
{
    const parsed = JSON.parse(text);

    applyPalette(parsed);
    restorePreview();
    updatePreview();

    //document.getElementById('palette-start').style.display = 'none';
    document.getElementById('palette-form').style.display = 'block';
    document.getElementById('palette-right').style.display = 'block';
    document.getElementById('palette-save').style.display = 'inline-block';

    initialized = true;
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('#palette-form').addEventListener('submit', function (event)
    {
        event.preventDefault();
        send(this.getAttribute('action'), this.getAttribute('method'), new FormData(this)).then();
    });

    document.querySelector('#palette-load-default').addEventListener('click', function (event)
    {
        if (initialized && !confirm("This will discard any changes you made to the palette. Do you want to continue?"))
        {
            return;
        }

        fetch('/palette/get-default', {
            method: 'GET',
        }).then((response) => {
            if (response.status < 400)
            {
                response.text().then((text) => {
                    afterLoad(text);
                });
            }
        });
    });

    document.querySelector("#palette-load-custom-button").addEventListener('click', function (event)
    {
        if (initialized && !confirm("This will discard any changes you made to the palette. Do you want to continue?"))
        {
            event.preventDefault();
        }
    });

    const customInput = document.querySelector('#palette-load-custom-input');
    customInput.addEventListener('change', function (event)
    {
        const file = customInput.files[0];
        let formData = new FormData();
        formData.append('object', file);

        fetch('/palette/extract', {
            method: 'POST',
            body: formData
        }).then((response) => {
            if (response.status < 400)
            {
                response.text().then((text) => {
                    afterLoad(text);
                });
            }
            else
            {
                response.text().then((text) => {
                    const parsed = JSON.parse(text);
                    alert(parsed.error);
                });
            }
        });
    });



    document.getElementById('showAdvanced').checked = false;
    document.querySelector('#showAdvanced').addEventListener('click', function (event)
    {
        if (document.getElementById('showAdvanced').checked)
            document.querySelector('#advanced-options').setAttribute('class', '');
        else
            document.querySelector('#advanced-options').setAttribute('class', 'd-none');
    });

    /////
    // Preview
    /////

    document.querySelector('#palette-preview-button').addEventListener('click', function (event)
    {
        event.preventDefault();
        updatePreview();
    });

    document.querySelector("#palette-preview-maximize").addEventListener('click', function ()
    {
        maximizePreview();
    });

    document.querySelector("#palette-preview-restore").addEventListener('click', function ()
    {
        restorePreview();
    });

    document.querySelector("#palette-preview-save").addEventListener("click", function ()
    {
        download(previewFile);
    });

    document.querySelector("#own-image-preview-file").addEventListener('change', function ()
    {
        updatePreview();
    });
});

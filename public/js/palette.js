'use strict';

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

document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('#palette-form').addEventListener('submit', function (event)
    {
        event.preventDefault();
        send(this.getAttribute('action'), this.getAttribute('method'), new FormData(this)).then();
    });

    document.querySelector('#palette-load-default').addEventListener('click', function (event)
    {
        fetch('/palette/get-default', {
            method: 'GET',
        }).then((response) => {
            if (response.status < 400)
            {
                response.text().then((text) => {
                    const parsed = JSON.parse(text);

                    applyPalette(parsed);

                    document.getElementById('palette-start').style.display = 'none';
                    document.getElementById('palette-form').style.display = 'block';
                });
            }
        });
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
                    const parsed = JSON.parse(text);

                    applyPalette(parsed);

                    document.getElementById('palette-start').style.display = 'none';
                    document.getElementById('palette-form').style.display = 'block';
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
});

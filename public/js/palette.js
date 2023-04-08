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

function componentToHex(c)
{
    let hex = c.toString(16);
    return hex.length === 1 ? "0" + hex : hex;
}

const RGBToHSL = (rgb) => {
    let r = rgb.r;
    let g = rgb.g;
    let b = rgb.b;

    r /= 255;
    g /= 255;
    b /= 255;
    const l = Math.max(r, g, b);
    const s = l - Math.min(r, g, b);
    const h = s
        ? l === r
            ? (g - b) / s
            : l === g
                ? 2 + (b - r) / s
                : 4 + (r - g) / s
        : 0;
    return {
        h: parseInt(Math.round(60 * h < 0 ? 60 * h + 360 : 60 * h)),
        s: parseInt(Math.round(100 * (s ? (l <= 0.5 ? s / (2 * l - s) : s / (2 - (2 * l - s))) : 0))),
        l: parseInt(Math.round(((100 * (2 * l - s)) / 2)))
    };
};

const HSLToRGB = (hsl) => {
    let h = hsl.h;
    let s = hsl.s;
    let l = hsl.l;

    s /= 100;
    l /= 100;
    const k = n => (n + h / 30) % 12;
    const a = s * Math.min(l, 1 - l);
    const f = n =>
        l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)));
    return { r: parseInt(Math.round(255 * f(0))), g: parseInt(Math.round(255 * f(8))), b: parseInt(Math.round(255 * f(4))) };
};

function hexToRGB(hex)
{
    return {
        r: parseInt(hex.substring(1, 3), 16),
        g: parseInt(hex.substring(3, 5), 16),
        b: parseInt(hex.substring(5, 7), 16)
    };
}

function rgbToHex(rgb)
{
    return '#' + componentToHex(rgb.r) + componentToHex(rgb.g) + componentToHex(rgb.b);
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

    ////
    // Row hue
    ///

    function getStartElement(rowIndex)
    {
        return document.querySelector('#palette-color-' + rowIndex * 12);
    }

    const modalRowIndexInput = document.querySelector('#update-row-hue-row-index');
    const hueSlider = document.querySelector('#update-row-hue-input');

    const updateColorSliderPreview = function ()
    {
        let hsl = { h: hueSlider.value, s: 50, l: 50 };
        let rgb = HSLToRGB(hsl);
        document.querySelector('#update-row-hue-preview').style.color = rgbToHex(rgb);
    };

    document.querySelectorAll('.update-row-hue').forEach(function (elem)
    {
        elem.addEventListener('click', function (event)
        {
            const rowIndex = parseInt(elem.attributes['data-row-index'].value);
            modalRowIndexInput.value = rowIndex;

            let startElem = getStartElement(rowIndex);
            let startHSL = RGBToHSL(hexToRGB(startElem.value));
            hueSlider.value = startHSL.h;
            updateColorSliderPreview();
        });
    });

    document.querySelector('#update-row-hue-apply').addEventListener('click', function ()
    {
        const rowIndex = parseInt(modalRowIndexInput.value);
        const startColor = rowIndex * 12;
        const endColor = ((rowIndex + 1) * 12) - 1;
        const selectedHue = parseInt(hueSlider.value);
        const startElem = getStartElement(rowIndex);
        const startHSL = RGBToHSL(hexToRGB(startElem.value));
        const hueOffset = selectedHue - startHSL.h;

        for (let colorIndex = startColor; colorIndex <= endColor; colorIndex++)
        {
            let colorElem = document.querySelector('#palette-color-' + colorIndex);

            let rgbIn = hexToRGB(colorElem.value);
            let hsl = RGBToHSL(rgbIn);
            hsl.h = (hsl.h + hueOffset) % 360;
            let rgbOut = HSLToRGB(hsl);

            colorElem.value = rgbToHex(rgbOut);
        }

        updatePreview();
    });

    hueSlider.addEventListener('change', updateColorSliderPreview);

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

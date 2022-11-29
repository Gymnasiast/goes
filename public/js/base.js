'use strict'

async function send(url, method, data)
{
    const response = await fetch(url, {
        method: method,
        body: data
    });

    if (response.status >= 400)
    {
        response.text().then((text) => {
            const parsed = JSON.parse(text);
            alert(parsed.error);
        });

    }
    else
    {
        // Offer up the file for download.
        response.blob().then((file) => {
            const cd = response.headers.get('Content-Disposition');
            // Strip out attachment; filename=
            const filename = cd.substring(21);

            const file2 = new File([file], filename, {
                type: file.type,
            });
            download(file2);
        });
    }
}

function download(file)
{
    const link = document.createElement('a');
    const url = URL.createObjectURL(file);

    link.href = url;
    link.download = file.name;
    document.body.appendChild(link);
    link.click();

    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
}

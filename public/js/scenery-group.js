'use strict';

let dragged = null;

document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('#scenery-group-form').addEventListener('submit', function (event)
    {
        event.preventDefault();
        let form = this;

        const identifiers = document.getElementsByName('identifiers[]');
        if (identifiers.length === 0)
        {
            alert('You did not add any entries!')
            return;
        }

        const data = new FormData(form);
        // Just to be sure, saves on bandwidth too.
        data.delete('entries');
        console.log(data);
        send(form.getAttribute('action'), form.getAttribute('method'), data).then();
    });

    document.querySelector('#add-by-upload').addEventListener('click', function (event) {
        const entriesInput = document.getElementById('entries');
        const entries = entriesInput.files;
        if (entries.length >= 1024)
        {
            alert('You can only select 1024 files at a time!')
            return;
        }

        const entriesListDOM = document.querySelector('#entries-list');
        const objectssPromise = getIdentifiers(entries);
        objectssPromise.then((objects) => {
            entriesInput.value = '';
            if (objects.length === 0)
            {
                console.error('Leeg!');
            }
            for (let i = 0; i < objects.length; i++)
            {
                const object = objects[i];
                const identifier = object.identifier;

                const entryOuterBox = document.createElement('div');
                entriesListDOM.appendChild(entryOuterBox);
                entryOuterBox.className = 'entry-drag-outer-box';
                entryOuterBox.draggable = true;
                entryOuterBox.setAttribute('data-identifier', identifier);
                entryOuterBox.addEventListener('dragstart', function (event) {
                    dragged = event.target;
                    event.target.classList.add("dragging");
                });
                entryOuterBox.addEventListener('dragend', function (event) {
                    event.target.classList.remove("dragging");
                });
                entryOuterBox.addEventListener('dragenter', function (event) {
                    // highlight potential drop target when the draggable element enters it
                    if (event.target.draggable) {
                        event.target.classList.add("dragover");
                    }
                });
                entryOuterBox.addEventListener("dragleave", (event) => {
                    // reset background of potential drop target when the draggable element leaves it
                    if (event.target.draggable) {
                        event.target.classList.remove("dragover");
                    }
                });
                entryOuterBox.addEventListener('dragover', function (event) {
                   event.preventDefault();
                });
                entryOuterBox.addEventListener('drop', function(event) {
                    event.preventDefault();
                    // move dragged element to the selected drop target
                    if (event.target.draggable) {
                        event.target.classList.remove("dragover");
                        entriesListDOM.insertBefore(dragged, entryOuterBox);
                    }
                });
                // const entryInnerBox = document.createElement('div');
                // entryInnerBox.className = 'entry-drag-inner-box';
                // entryInnerBox.innerText = identifier;
                entryOuterBox.innerText = identifier;

                // entryInnerBox.addEventListener('dragenter', function (event) {
                //     // highlight potential drop target when the draggable element enters it
                //     if (entryOuterBox.draggable) {
                //         entryOuterBox.classList.add("dragover");
                //     }
                // });
                // entryInnerBox.addEventListener("dragleave", (event) => {
                //     // reset background of potential drop target when the draggable element leaves it
                //     if (entryOuterBox.draggable) {
                //         entryOuterBox.classList.remove("dragover");
                //     }
                // });
                // entryInnerBox.addEventListener('dragover', function (event) {
                //     event.preventDefault();
                // });
                // entryInnerBox.addEventListener('drop', function(event) {
                //     event.preventDefault();
                //     // move dragged element to the selected drop target
                //     if (entryOuterBox.draggable) {
                //         entryOuterBox.classList.remove("dragover");
                //         entriesListDOM.insertBefore(dragged, entryOuterBox);
                //     }
                // });

                const entryInnerInput = document.createElement('input');
                entryInnerInput.type = 'hidden';
                entryInnerInput.name = 'identifiers[]';
                entryInnerInput.value = identifier;
                const entryInnerRemove = document.createElement('span');
                entryInnerRemove.className = 'remove-object';
                entryInnerRemove.innerText = '×';
                entryInnerRemove.setAttribute('data-identifier', identifier);
                entryInnerRemove.addEventListener('click', function() {
                    entryOuterBox.remove();
                });

                // entryInnerBox.appendChild(entryInnerInput);
                // entryOuterBox.appendChild(entryInnerBox);
                entryOuterBox.appendChild(entryInnerInput);
                entryOuterBox.appendChild(entryInnerRemove);
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

async function getIdentifiers(entries)
{
    let promises = [];
    for (let i = 0; i < entries.length; i++)
    {
        let formData = new FormData();
        formData.append('object', entries[i]);

        promises.push(fetch('/get-identifier', {
            method: 'POST',
            body: formData
        }));
    }

    return Promise.all(promises).then((responses) =>
    {
        let subPromises = [];
        responses.forEach((response) => {
            if (response.ok) {
                subPromises.push(response.json());
            }
        });

        return Promise.all(subPromises).then((subResponses) =>
        {
            let identifiers = [];
            subResponses.forEach((subResponse) =>
            {
                identifiers.push(subResponse);
            });

            return identifiers;
        })
    });
}

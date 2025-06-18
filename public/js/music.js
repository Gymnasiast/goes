'use strict';

let currentNumTrackFields = 1;

function addAnotherTrack()
{
    if (currentNumTrackFields >= 20)
    {
        return;
    }

    const container = document.getElementById('musicTracksContainer');

    const newTrackId = ++currentNumTrackFields;

    const fragment = new DocumentFragment();

    const title = document.createElement('h3');
    title.innerText = `Track ${newTrackId}`;
    fragment.append(title);

    const fileWrapper = document.createElement('div');
    fileWrapper.classList.add('mb-3');
    fileWrapper.innerHTML =
        `<label for="track_${newTrackId}_upload" class="form-label">Upload</label>` +
            `<input type="file" class="form-control" id="track_${newTrackId}_upload" accept="audio/ogg" name="track_${newTrackId}_upload" aria-describedby="track_${newTrackId}_upload_help">` +
        `<div id="track_${newTrackId}_upload_help" class="form-text">The music file itself.</div>`
    fragment.append(fileWrapper);

    const nameWrapper = document.createElement('div');
    nameWrapper.classList.add('mb-3');
    nameWrapper.innerHTML =
        `<label for="track_${newTrackId}_name" class="form-label">Track name</label>` +
            `<input type="text" class="form-control" id="track_${newTrackId}_name" name="track_${newTrackId}_name" aria-describedby="track_${newTrackId}_name_help">` +
        `<div id="track_${newTrackId}_name_help" class="form-text">Name of this track. May be left blank. E.g.: <i>Airtime Junkies</i></div>`
    fragment.append(nameWrapper);

    const composerWrapper = document.createElement('div');
    composerWrapper.classList.add('mb-3');
    composerWrapper.innerHTML =
        `<label for="track_${newTrackId}_composer" class="form-label">Composer</label>` +
            `<input type="text" class="form-control" id="track_${newTrackId}_composer" name="track_${newTrackId}_composer" aria-describedby="track_${newTrackId}_composer_help">` +
        `<div id="track_${newTrackId}_composer_help" class="form-text">Composer of this track. May be left blank. E.g.: <i>Jalmaan</i></div>`
    fragment.append(composerWrapper);

    container.append(fragment);

    if (currentNumTrackFields >= 20)
    {
        document.querySelector('#addAnotherTrack').disabled = true;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('#music-form').addEventListener('submit', function (event)
    {
        event.preventDefault();
        send(this.getAttribute('action'), this.getAttribute('method'), new FormData(this)).then();
    });

    document.getElementById('showAdvanced').checked = false;
    document.querySelector('#showAdvanced').addEventListener('click', function (event)
    {
         if (document.getElementById('showAdvanced').checked)
             document.querySelector('#advanced-options').setAttribute('class', '');
         else
             document.querySelector('#advanced-options').setAttribute('class', 'd-none');
    });

    document.querySelector('#addAnotherTrack').addEventListener('click', addAnotherTrack);
});

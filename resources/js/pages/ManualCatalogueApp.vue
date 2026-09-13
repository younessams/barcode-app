<script setup>
import { ref } from 'vue';

defineProps({
    generateUrl: { type: String, required: true },
    csrfToken: { type: String, required: true },
    error: { type: String, default: '' },
    result: { type: Object, default: null },
});

const fileInput = ref(null);
const selectedFileName = ref('');
const isDragging = ref(false);

function showFile(file) {
    if (!file) return;

    selectedFileName.value = file.name;
}

function onFileChange() {
    showFile(fileInput.value?.files?.[0]);
}

function onDragEnter(event) {
    event.preventDefault();
    isDragging.value = true;
}

function onDragLeave(event) {
    event.preventDefault();
    isDragging.value = false;
}

function onDrop(event) {
    event.preventDefault();
    isDragging.value = false;

    const file = event.dataTransfer?.files?.[0];

    if (!file || !/\.(xlsx|xls)$/i.test(file.name)) {
        return;
    }

    const transfer = new DataTransfer();
    transfer.items.add(file);
    fileInput.value.files = transfer.files;

    showFile(file);
}
</script>

<template>
    <main>
        <section class="panel">
            <div class="panel-body">
                <div v-if="error" class="alert" role="alert">
                    {{ error }}
                </div>

                <section v-if="result" class="result">
                    <p class="result-title">
                        PDF genere avec succes
                    </p>

                    <p class="result-info">
                        {{ result.items }} articles
                        &middot;
                        {{ result.pages }} page(s) A4
                    </p>

                    <div class="result-actions">
                        <a class="button" :href="result.downloadUrl">
                            Telecharger
                        </a>

                        <a class="secondary-button" :href="result.printUrl" target="_blank" rel="noopener">
                            Ouvrir / Imprimer
                        </a>
                    </div>
                </section>

                <form method="post" :action="generateUrl" enctype="multipart/form-data">
                    <input type="hidden" name="_token" :value="csrfToken">

                    <h2>Fichier Excel</h2>

                    <label
                        id="dropzone"
                        class="dropzone"
                        :class="{ 'has-file': selectedFileName, 'is-dragging': isDragging }"
                        for="excel_file"
                        @dragenter="onDragEnter"
                        @dragover="onDragEnter"
                        @dragleave="onDragLeave"
                        @drop="onDrop"
                    >
                        <input
                            id="excel_file"
                            ref="fileInput"
                            class="file-input"
                            type="file"
                            name="excel_file"
                            accept=".xlsx,.xls"
                            required
                            @change="onFileChange"
                        >

                        <span class="upload-state">
                            <strong>Choisir un fichier Excel</strong>

                            <span class="secondary-button">
                                Parcourir
                            </span>

                            <span class="field-help">
                                XLSX ou XLS
                            </span>
                        </span>

                        <span class="selected-state">
                            <strong id="file-name">{{ selectedFileName }}</strong>

                            <span class="field-help">
                                Fichier pret
                            </span>

                            <span class="secondary-button">
                                Modifier
                            </span>
                        </span>
                    </label>

                    <div class="requirements">
                        <p>Colonnes requises</p>

                        <code>Code Article</code>
                        <code>Designation / Désignation</code>
                        <code>Emplacement</code>
                    </div>

                    <button class="button" type="submit">
                        Generer le catalogue QR
                    </button>
                </form>
            </div>
        </section>

        <section class="preview-panel">
            <div class="preview-title">
                <h2>Apercu A4</h2>

                <span class="field-help">
                    2 x 6 · 12 articles / page
                </span>
            </div>

            <div class="a4">
                <div v-for="index in 12" :key="index" class="mock-card">
                    <div class="mock-qr"></div>

                    <div class="mock-info">
                        <div class="mock-code"></div>
                        <div class="mock-text"></div>
                        <div class="mock-text"></div>
                        <div class="mock-location"></div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>

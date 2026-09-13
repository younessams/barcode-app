<script setup>
defineProps({
    storeUrl: { type: String, required: true },
    csrfToken: { type: String, required: true },
    old: { type: Object, default: () => ({ name: '', zone: '' }) },
    errors: { type: Object, default: () => ({}) },
    inventories: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="layout">
        <section>
            <h2>Creer un inventaire</h2>

            <div v-if="errors.name" class="alert">
                {{ errors.name }}
            </div>

            <form method="post" :action="storeUrl">
                <input type="hidden" name="_token" :value="csrfToken">

                <label for="name">Nom</label>
                <input id="name" name="name" required maxlength="120" :value="old.name">

                <label for="zone">Zone <span style="font-weight:400">(optionnel)</span></label>
                <input id="zone" name="zone" maxlength="120" :value="old.zone">

                <button type="submit">Commencer l'inventaire</button>
            </form>
        </section>

        <section>
            <h2>Inventaires existants</h2>

            <p v-if="inventories.length === 0" class="empty">Aucun inventaire pour le moment.</p>

            <div v-else class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Zone</th>
                            <th>Statut</th>
                            <th>References</th>
                            <th>Total</th>
                            <th>Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="inventory in inventories" :key="inventory.uuid">
                            <td><strong>{{ inventory.name }}</strong></td>
                            <td>{{ inventory.zone || '-' }}</td>
                            <td><span class="status" :class="inventory.status">{{ inventory.statusText }}</span></td>
                            <td>{{ inventory.itemsCount }}</td>
                            <td>{{ inventory.totalQuantity }}</td>
                            <td>{{ inventory.startedAt }}</td>
                            <td>
                                <div class="actions">
                                    <a class="button secondary" :href="inventory.showUrl">{{ inventory.isCompleted ? 'Consulter' : 'Continuer' }}</a>
                                    <a class="button secondary" :href="inventory.exportUrl">Excel</a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

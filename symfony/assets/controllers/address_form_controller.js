import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["countrySelect", "dependentFieldsWrapper"];
    static values = {
        updateUrl: String
    }

    connect() {
        // Optional: Nur initial updaten, wenn wirklich ein Land gewählt ist
        // if (this.countrySelectTarget.value) {
        //    this.update();
        // }
    }

    async update() {
        const form = this.element;
        const formData = new FormData(form);
        const url = this.updateUrlValue; // Wir nehmen nur die explizit gesetzte URL

        if (!url) {
            console.error('Keine updateUrlValue für address-form Controller gesetzt.');
            return; // Early exit
        }

        if (!this.hasDependentFieldsWrapperTarget) {
            console.error('Target "dependentFieldsWrapper" nicht im Formular gefunden.');
            return; // Early exit
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                body: formData,
                // Header, um explizit HTML anzufordern (optional, aber gute Praxis)
                headers: {
                    'Accept': 'text/html',
                }
            });

            if (response.ok) {
                const html = await response.text(); // HTML als Text erhalten
                this.dependentFieldsWrapperTarget.innerHTML = html; // Inhalt ersetzen
            } else {
                console.error(`Fehler beim Aktualisieren des Formulars: ${response.status} ${response.statusText}`);
            }
        } catch (error) {
            console.error('Netzwerkfehler oder anderer JavaScript-Fehler:', error);
        }
    }
}
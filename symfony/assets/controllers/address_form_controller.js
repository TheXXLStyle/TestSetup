import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["countrySelect", "dependentFieldsWrapper"];
    static values = {
        updateUrl: String
    }

    connect() {
    }

    async update() {
        const form = this.element;
        const formData = new FormData(form);
        const url = this.updateUrlValue; // We only use the explicitly set URL

        if (!url) {
            console.error('No updateUrlValue set for address-form controller.');
            return;
        }

        if (!this.hasDependentFieldsWrapperTarget) {
            console.error('Target "dependentFieldsWrapper" not found in the form.');
            return;
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                body: formData,

                headers: {
                    'Accept': 'text/html',
                }
            });

            if (response.ok) {
                const html = await response.text(); // Get HTML as text
                this.dependentFieldsWrapperTarget.innerHTML = html; // Replace content
            } else {
                console.error(`Error updating the form: ${response.status} ${response.statusText}`);
            }
        } catch (error) {
            console.error('Network error or other JavaScript error:', error);
        }
    }
}
import IntegrationsEditView from 'views/admin/integrations/edit';

/**
 * QuickBooks integration admin view.
 * Extends the standard edit form with an OAuth Connect button.
 */
export default class QuickBooksIntegrationView extends IntegrationsEditView {

    events = {
        /** @this QuickBooksIntegrationView */
        'click [data-action="connectQuickBooks"]': function () {
            this.actionConnectQuickBooks();
        },
    }

    afterRender() {
        super.afterRender();
        this.renderConnectSection();
    }

    renderConnectSection() {
        const isConnected = !!this.model.get('realmId');
        const realmId = this.model.get('realmId') ?? '';
        const connectedAt = this.model.get('connectedAt') ?? '';

        const statusHtml = isConnected
            ? `<span style="color:#2b7de9;font-weight:600">&#10003; Connected</span>
               <span style="color:#666;margin-left:10px;font-size:12px">Realm: ${realmId}</span>
               <span style="color:#999;margin-left:8px;font-size:12px">${connectedAt}</span>`
            : `<span style="color:#999">Not connected to QuickBooks</span>`;

        const btnLabel = isConnected ? 'Reconnect to QuickBooks' : 'Connect to QuickBooks';

        const html = `
            <div class="qb-connect-wrap" style="
                margin-top: 20px;
                padding: 16px 18px;
                background: #f7f9fc;
                border: 1px solid #dce1ea;
                border-radius: 6px;
            ">
                <div style="margin-bottom:12px;font-size:13px">${statusHtml}</div>
                <button class="btn btn-primary btn-sm" data-action="connectQuickBooks">
                    ${btnLabel}
                </button>
                <p style="margin-top:10px;margin-bottom:0;font-size:12px;color:#888">
                    Save your Client ID and Client Secret first, then click Connect.
                    A popup will open to authorize with QuickBooks.
                </p>
            </div>
        `;

        this.$el.find('.panel-body').last().append(html);
    }

    actionConnectQuickBooks() {
        const clientId = this.model.get('clientId');

        if (!clientId) {
            Espo.Ui.warning('Save your Client ID and Client Secret first.');
            return;
        }

        const siteUrl = (this.getConfig().get('siteUrl') ?? window.location.origin).replace(/\/$/, '');
        const redirectUri = `${siteUrl}?entryPoint=QuickBooksOauthCallback`;
        const state = Math.random().toString(36).slice(2) + Date.now().toString(36);

        const authUrl =
            'https://appcenter.intuit.com/connect/oauth2' +
            '?client_id=' + encodeURIComponent(clientId) +
            '&scope=com.intuit.quickbooks.accounting' +
            '&redirect_uri=' + encodeURIComponent(redirectUri) +
            '&response_type=code' +
            '&state=' + state;

        const popup = window.open(authUrl, 'qb-oauth', 'width=650,height=720,left=200,top=100');

        if (!popup) {
            Espo.Ui.error('Pop-up was blocked. Please allow pop-ups for this site and try again.');
            return;
        }

        const onMessage = (event) => {
            if (!event.data || event.data.name !== 'quickBooksOAuth') return;

            window.removeEventListener('message', onMessage);

            if (event.data.status === 'success') {
                Espo.Ui.success('QuickBooks connected successfully.');
                this.model.fetch().then(() => {
                    this.$el.find('.qb-connect-wrap').remove();
                    this.renderConnectSection();
                });
            } else {
                Espo.Ui.error('QuickBooks connection failed. Check the browser console for details.');
            }
        };

        window.addEventListener('message', onMessage);

        // Clean up listener if popup closes without posting a message
        const pollClosed = setInterval(() => {
            if (popup.closed) {
                clearInterval(pollClosed);
                window.removeEventListener('message', onMessage);
            }
        }, 800);
    }
}

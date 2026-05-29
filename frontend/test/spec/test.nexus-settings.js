describe('NexusSettingsView', () => {
    let NexusSettingsView;

    beforeAll(done => {
        require(['nexus:views/admin/nexus-settings'], ViewClass => {
            NexusSettingsView = ViewClass;
            done();
        });
    });

    function createInstance(props = {}) {
        const inst = Object.create(NexusSettingsView.prototype);
        inst.nexusSettings = {};
        inst.statusMsg = '';
        Object.assign(inst, props);
        return inst;
    }

    describe('#data', () => {
        it('returns default nexusUrl when settings are empty', () => {
            expect(createInstance().data().nexusUrl).toBe('http://potpie.local:8000');
        });

        it('returns nexusUrl from settings when set', () => {
            const view = createInstance({ nexusSettings: { nexusUrl: 'http://my.server:5000' } });
            expect(view.data().nexusUrl).toBe('http://my.server:5000');
        });

        it('defaults nexusUsername to empty string', () => {
            expect(createInstance().data().nexusUsername).toBe('');
        });

        it('returns nexusUsername from settings when set', () => {
            const view = createInstance({ nexusSettings: { nexusUsername: 'admin' } });
            expect(view.data().nexusUsername).toBe('admin');
        });

        it('defaults nexusEnabled to false', () => {
            expect(createInstance().data().nexusEnabled).toBe(false);
        });

        it('returns nexusEnabled: true from settings', () => {
            const view = createInstance({ nexusSettings: { nexusEnabled: true } });
            expect(view.data().nexusEnabled).toBe(true);
        });

        it('defaults nexusRagEnabled to true when not in settings', () => {
            expect(createInstance().data().nexusRagEnabled).toBe(true);
        });

        it('returns nexusRagEnabled: false when explicitly set to false', () => {
            const view = createInstance({ nexusSettings: { nexusRagEnabled: false } });
            expect(view.data().nexusRagEnabled).toBe(false);
        });

        it('returns nexusRagEnabled: true when explicitly set to true', () => {
            const view = createInstance({ nexusSettings: { nexusRagEnabled: true } });
            expect(view.data().nexusRagEnabled).toBe(true);
        });

        it('includes statusMsg in returned data', () => {
            const view = createInstance({ statusMsg: '<span>Saved.</span>' });
            expect(view.data().statusMsg).toBe('<span>Saved.</span>');
        });
    });
});

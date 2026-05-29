describe('NexusAssistantView', () => {
    let NexusAssistantView;

    beforeAll(done => {
        require(['nexus:views/panels/nexus-assistant'], ViewClass => {
            NexusAssistantView = ViewClass;
            done();
        });
    });

    function createInstance(props = {}) {
        const inst = Object.create(NexusAssistantView.prototype);
        inst._lastPrompt = null;
        Object.assign(inst, props);
        return inst;
    }

    /**
     * Returns a minimal $el mock and a reference to the element map so tests
     * can inspect which selectors were touched and what was set on them.
     */
    function makeElMock() {
        const els = {};
        const el = sel => {
            if (!els[sel]) {
                els[sel] = {
                    _text: undefined, _html: undefined, _visible: undefined,
                    text(v) { if (v !== undefined) this._text = v; return this; },
                    html(v) { if (v !== undefined) this._html = v; return this; },
                    show()  { this._visible = true;  return this; },
                    hide()  { this._visible = false; return this; },
                    prop()  { return this; },
                    val()   { return ''; },
                    is()    { return false; },
                };
            }
            return els[sel];
        };
        return { els, $el: { find: el } };
    }

    // ------------------------------------------------------------------
    // data()
    // ------------------------------------------------------------------

    describe('#data', () => {
        it('returns nexusEnabled: true when config returns truthy', () => {
            const view = createInstance({ getConfig: () => ({ get: () => true }) });
            expect(view.data().nexusEnabled).toBe(true);
        });

        it('returns nexusEnabled: false when config returns false', () => {
            const view = createInstance({ getConfig: () => ({ get: () => false }) });
            expect(view.data().nexusEnabled).toBe(false);
        });

        it('returns nexusEnabled: true when config returns null (null is not false)', () => {
            const view = createInstance({ getConfig: () => ({ get: () => null }) });
            expect(view.data().nexusEnabled).toBe(true);
        });
    });

    // ------------------------------------------------------------------
    // onKeydown()
    // ------------------------------------------------------------------

    describe('#onKeydown', () => {
        it('calls onAsk on Ctrl+Enter', () => {
            const view = createInstance();
            spyOn(view, 'onAsk');
            view.onKeydown({ ctrlKey: true, metaKey: false, key: 'Enter' });
            expect(view.onAsk).toHaveBeenCalled();
        });

        it('calls onAsk on Meta+Enter (macOS)', () => {
            const view = createInstance();
            spyOn(view, 'onAsk');
            view.onKeydown({ ctrlKey: false, metaKey: true, key: 'Enter' });
            expect(view.onAsk).toHaveBeenCalled();
        });

        it('does not call onAsk on plain Enter', () => {
            const view = createInstance();
            spyOn(view, 'onAsk');
            view.onKeydown({ ctrlKey: false, metaKey: false, key: 'Enter' });
            expect(view.onAsk).not.toHaveBeenCalled();
        });

        it('does not call onAsk on Ctrl+other key', () => {
            const view = createInstance();
            spyOn(view, 'onAsk');
            view.onKeydown({ ctrlKey: true, metaKey: false, key: 'a' });
            expect(view.onAsk).not.toHaveBeenCalled();
        });
    });

    // ------------------------------------------------------------------
    // onAsk() — guard only (no Ajax needed)
    // ------------------------------------------------------------------

    describe('#onAsk', () => {
        it('returns early without side effects when prompt is empty', () => {
            const { els, $el } = makeElMock();
            els['.nexus-prompt'] = { val: () => '   ' };
            const view = createInstance({ $el });

            expect(() => view.onAsk()).not.toThrow();
            // The loading indicator is only touched after the guard — never accessed here
            expect(els['.nexus-loading']).toBeUndefined();
        });
    });

    // ------------------------------------------------------------------
    // _showResult()
    // ------------------------------------------------------------------

    describe('#_showResult', () => {
        it('displays r.reply as primary text', () => {
            const { els, $el } = makeElMock();
            createInstance({ $el })._showResult({ reply: 'Hello!' });
            expect(els['.nexus-result-text']._text).toBe('Hello!');
        });

        it('falls back to r.result_text when no reply', () => {
            const { els, $el } = makeElMock();
            createInstance({ $el })._showResult({ result_text: 'Done.' });
            expect(els['.nexus-result-text']._text).toBe('Done.');
        });

        it('falls back to r.response when no reply or result_text', () => {
            const { els, $el } = makeElMock();
            createInstance({ $el })._showResult({ response: 'Response.' });
            expect(els['.nexus-result-text']._text).toBe('Response.');
        });

        it('falls back to r.content as last resort', () => {
            const { els, $el } = makeElMock();
            createInstance({ $el })._showResult({ content: 'Content.' });
            expect(els['.nexus-result-text']._text).toBe('Content.');
        });

        it('renders html placeholder when no text field is present', () => {
            const { els, $el } = makeElMock();
            createInstance({ $el })._showResult({});
            expect(els['.nexus-result-text']._html).toBeTruthy();
            expect(els['.nexus-result-text']._text).toBeUndefined();
        });

        it('builds meta string from model_used and tier_used', () => {
            const { els, $el } = makeElMock();
            createInstance({ $el })._showResult({ reply: 'x', model_used: 'qwen3', tier_used: 'gpu' });
            expect(els['.nexus-result-meta']._text).toBe('qwen3 · gpu');
        });

        it('omits empty tier from meta', () => {
            const { els, $el } = makeElMock();
            createInstance({ $el })._showResult({ reply: 'x', model_used: 'qwen3' });
            expect(els['.nexus-result-meta']._text).toBe('qwen3');
        });

        it('sets meta to empty string when no model or tier', () => {
            const { els, $el } = makeElMock();
            createInstance({ $el })._showResult({ reply: 'x' });
            expect(els['.nexus-result-meta']._text).toBe('');
        });

        it('shows the nexus-result container', () => {
            const { els, $el } = makeElMock();
            createInstance({ $el })._showResult({ reply: 'x' });
            expect(els['.nexus-result']._visible).toBe(true);
        });
    });
});

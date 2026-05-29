// Minimal stub for the EspoCRM View base class.
// The real 'view' module depends on bullbone which is not built in this
// checkout. Nexus view tests only exercise data() / logic methods via
// Object.create() so the full View machinery is not needed.

(function () {
    define('view', function () {
        return class View {};
    });
}());

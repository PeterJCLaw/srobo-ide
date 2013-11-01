
function UsageSorter(initial, save_hanlder) {
        // ensure we don't modify the original list
    this._list = initial.concat([]);
    this._save_hanlder = save_hanlder;

    this.notify_use = function(item) {
        this._list.push(item);
        this._save_hanlder(this._list);
    }

    this.sort = function(items) {
        // ensure we don't modify the original list
        var items = items.concat([]);
        var sorted = [];
        for (var i = this._list.length - 1; i >= 0 ; i--) {
            var item = this._list[i];
            var idx = items.indexOf(item);
            if (idx > -1) {
                // add it to our list, remove from the input
                sorted.push(item);
                items.splice(idx, 1);
            }
        }
        return sorted.concat(items);
    }
}

// node require() based exports.
if (typeof(exports) != 'undefined') {
    exports.UsageSorter = UsageSorter;
}

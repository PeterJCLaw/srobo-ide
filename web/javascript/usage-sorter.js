
/**
 * Class to sort a list based on a usage pattern of members in that list.
 * @param initial: An array of items to seed the usage history, usually
 *          from a previous save.
 * @param save_handler: A function accepting a single array argument, which
 *          will be called to store the current usage history whenever it changes.
 * @param max_len: (optional) The maximum length of the usage array.
 *          Defaults to 20 items.
 */
function UsageSorter(initial, save_hanlder, max_len) {
    // ensure we don't modify the original list
    this._list = initial.concat([]);
    this._save_hanlder = save_hanlder;
    this._max_len = max_len || 20;

    this.notify_use = function(item) {
        this._list.push(item);
        this._trim();
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

    this._trim = function() {
        var extra = this._list.length - this._max_len;
        if (extra > 0) {
            this._list = this._list.slice(extra);
        }
    }

    // ensure that we start with an initial list that's the right size
    this._trim();
}

// node require() based exports.
if (typeof(exports) != 'undefined') {
    exports.UsageSorter = UsageSorter;
}

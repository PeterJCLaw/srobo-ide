
/**
 * Class to sort a list based on a usage pattern of members in that list.
 * @param initial: An array of items to seed the usage history, usually
 *          from a previous save.
 * @param save_handler: A function accepting a single array argument, which
 *          will be called to store the current usage history whenever it changes.
 */
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

# Test fixtures naming

- `f*` - count-based file with counter suffix
- `i` - ignored
- `c` - committed
- `u` - uncommitted
- `d` - deleted
- `d_` - directory
- `sub_` - sub-directory or file; used for testing wildcard name matching (i.e. `d_1/f1` vs `d_1/sub_f1`)
- `f*_l` - symlink to a file or dir (i.e. `f1_l` is a symlink to `f1`)

## Examples
- `f1`, `f2` - files `f1` and `f2`
- `d_1` - directory `d_1`
- `d_1/f1` - file `f1` in directory `d_1`.
- `d_ui/sub_ui` - file `sub_ui` is uncommitted and ignored and is located
in uncommitted and ignored directory `d_ui`.

/// Returns [value] only if it's present in [validValues], otherwise `null`.
///
/// `DropdownButton`/`DropdownButtonFormField` assert that their current
/// value matches *exactly one* item in `items` - if the item list hasn't
/// finished loading yet (e.g. categories/years/users fetched async after
/// first build) or no longer contains what used to be a valid selection
/// (data changed, was deleted, or a year became the active year and
/// dropped out of an "other years" list), the widget throws instead of
/// just showing nothing selected. Wrap any dropdown value that comes from
/// async data or external state with this rather than passing it directly.
T? safeDropdownValue<T>(T? value, Iterable<T> validValues) {
  if (value == null) return null;
  return validValues.contains(value) ? value : null;
}

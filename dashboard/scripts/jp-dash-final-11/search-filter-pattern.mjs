/** Classify SEARCH_FILTER_SORT control DOM pattern from inventory metadata. */
export function classifySearchFilterPattern(row) {
  const type = String(row.CONTROL_TYPE || "").toLowerCase();
  const label = String(row.CONTROL_LABEL || "").toLowerCase();
  const labelRaw = String(row.CONTROL_LABEL || "").trim();
  if (
    /no bookings found|no ledger entries|try clearing filters|try another filter|cancellation review/i.test(
      labelRaw,
    )
  ) {
    return "empty_state_panel";
  }
  if (/^apply filters$/i.test(labelRaw)) {
    return "filter_panel_apply";
  }
  if (/^reset filters$/i.test(labelRaw)) {
    return "filter_panel_reset";
  }
  if ((type === "div" || type === "section") && label.includes("filter")) {
    return "filter_panel";
  }
  if (label.includes("filters") && labelRaw.length > 48) {
    return "filter_panel";
  }
  if (type === "input_search" || type === "input" || type.includes("search")) {
    return "native_input";
  }
  if (type === "select") {
    return "native_select";
  }
  if (type.includes("checkbox")) {
    return "checkbox";
  }
  if (type.includes("radio")) {
    return "radio";
  }
  if (type.includes("date")) {
    return "date_filter";
  }
  if (type === "button" || label.includes("filter") || label.includes("sort")) {
    return "button_menu";
  }
  if (type === "div" || label.includes("all") || label.includes("rows per page")) {
    return "combobox";
  }
  if (label.includes("sort")) {
    return "listbox";
  }
  return "combobox";
}

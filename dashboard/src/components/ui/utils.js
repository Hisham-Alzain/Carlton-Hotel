export function cx(...values) {
  const classes = [];

  for (const value of values) {
    if (!value) continue;

    if (Array.isArray(value)) {
      classes.push(cx(...value));
      continue;
    }

    if (typeof value === "object") {
      for (const [key, enabled] of Object.entries(value)) {
        if (enabled) classes.push(key);
      }
      continue;
    }

    classes.push(String(value));
  }

  return classes.filter(Boolean).join(" ");
}

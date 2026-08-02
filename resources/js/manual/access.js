/**
 * Filter manual content so users only see topics and controls they can use.
 *
 * Gate shape on sections / howTos / actions / tip objects / troubles:
 *   anyOf: ['perm_a', 'perm_b']  — visible if user has any listed permission
 *   allOf: ['perm_a', 'perm_b']  — visible only if user has every listed permission
 * Omit both → visible to every signed-in user.
 *
 * Tips may be plain strings (always visible) or { text, anyOf?, allOf? }.
 */

export function canAccessGate(gate, can) {
  if (!gate || typeof gate === 'string') return true;

  const anyOf = gate.anyOf || [];
  const allOf = gate.allOf || [];

  if (anyOf.length && !anyOf.some((perm) => can(perm))) {
    return false;
  }

  if (allOf.length && !allOf.every((perm) => can(perm))) {
    return false;
  }

  return true;
}

function filterList(items, can) {
  if (!items?.length) return [];

  return items.filter((item) => canAccessGate(item, can));
}

export function filterManualForUser(sections, can) {
  return sections
    .filter((section) => canAccessGate(section, can))
    .map((section) => {
      const howTos = filterList(section.howTos, can);
      const actions = filterList(section.actions, can);
      const tips = filterList(section.tips, can);
      const troubles = filterList(section.troubles, can);

      if (!howTos.length && !actions.length && !tips.length && !troubles.length) {
        return null;
      }

      return {
        ...section,
        howTos,
        actions,
        tips,
        troubles,
      };
    })
    .filter(Boolean);
}

export function tipText(tip) {
  return typeof tip === 'string' ? tip : tip.text;
}

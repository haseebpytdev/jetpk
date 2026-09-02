function percentile(arr, p) {
  if (!arr.length) return null;
  const a = [...arr].sort((x,y)=>x-y);
  const idx = Math.min(a.length-1, Math.max(0, Math.ceil((p/100)*a.length)-1));
  return a[idx];
}

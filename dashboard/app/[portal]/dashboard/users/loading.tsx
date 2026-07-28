export default function UsersLoading() {
  return (
    <div className="animate-pulse space-y-4 p-6" aria-busy="true" aria-label="Loading users">
      <div className="h-8 w-48 rounded bg-gray-200" />
      <div className="h-24 rounded bg-gray-200" />
      <div className="h-64 rounded bg-gray-200" />
    </div>
  );
}

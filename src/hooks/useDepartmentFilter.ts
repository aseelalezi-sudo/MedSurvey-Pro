import { useState, useEffect } from 'react';
import { useAuthStore } from '../store/useAuthStore';

export function useDepartmentFilter(initialDepartment = 'all') {
  const { currentUser } = useAuthStore();
  const restrictedDepartment = currentUser?.role === 'head_of_department' ? currentUser.department : undefined;

  const [selectedDepartment, setSelectedDepartment] = useState<string>(
    restrictedDepartment || initialDepartment
  );

  useEffect(() => {
    if (restrictedDepartment && selectedDepartment !== restrictedDepartment) {
      setSelectedDepartment(restrictedDepartment);
    }
  }, [restrictedDepartment, selectedDepartment]);

  const effectiveDepartment = restrictedDepartment || (selectedDepartment !== 'all' ? selectedDepartment : undefined);

  return {
    selectedDepartment,
    setSelectedDepartment,
    restrictedDepartment,
    effectiveDepartment,
  };
}

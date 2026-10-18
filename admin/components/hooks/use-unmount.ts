import { useRef, useEffect } from "react"

/**
 * Hook that executes a callback when the component unmounts.
 *
 * @param callback Function to be called on component unmount
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export const useUnmount = (callback: (...args: Array<any>) => any) => {
  const ref = useRef(callback)

  // Запись в ref во время рендера запрещена (React Compiler): обновляем после коммита.
  useEffect(() => {
    ref.current = callback
  })

  useEffect(
    () => () => {
      ref.current()
    },
    []
  )
}

export default useUnmount

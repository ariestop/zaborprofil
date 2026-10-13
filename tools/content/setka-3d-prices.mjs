/**
 * Прайс-лист 3D забора со страницы https://zaborprofil.ru/zabor-3d-setka/ (цены ₽ за штуку, null — такого размера нет).
 * Данные перенесены без изменений из таблиц «Прайс-лист цены на панели 3Д забора», «Столбы», «Калитка», «Ворота распашные»
 * и «Тип крепления». Высоты и размеры записаны так же, как на старом сайте (с запятой).
 */

const PANEL_HEIGHTS = ['1,23 м', '1,53 м', '1,73 м', '2,03 м', '2,23 м', '2,40 м']

const byHeight = (heights, prices) => Object.fromEntries(heights.map((height, index) => [height, prices[index] ?? null]))

const panelSpecs = (coating, coatingChip, rod) => [
  { label: 'Покрытие', value: coating, chip: coatingChip },
  { label: 'Пруток', value: `${rod} мм`, chip: `пруток ${rod} мм` },
  { label: 'Ячейка', value: '200×50 мм', chip: 'ячейка 200×50 мм' },
  { label: 'Длина', value: '2,50 м', chip: 'длина 2,5 м' },
]

export const panelsPriceGroup = {
  label: 'Панели',
  rowsLabel: 'Высота панели',
  note: 'Рёбер жёсткости: у панели 1,23 м — 2; 1,53 и 1,73 м — 3; 2,03 и 2,23 м — 4; 2,40 м — 5.',
  rows: PANEL_HEIGHTS,
  options: [
    { title: '3D секция «Эконом»', group: 'Цинк', tone: 'blue', specs: panelSpecs('цинк (Zn)', '-', 3), prices: byHeight(PANEL_HEIGHTS, [953, 1132, 1161, 1321, 1442, 1616]) },
    { title: '3D секция «Эконом RAL»', group: 'Цинк + RAL', tone: 'green', specs: panelSpecs('цинк (Zn) + полимер RAL', '-', 3), prices: byHeight(PANEL_HEIGHTS, [1226, 1425, 1536, 1799, 1944, 2127]) },
    { title: '3D секция «Стандарт»', group: 'Цинк', tone: 'blue', specs: panelSpecs('цинк (Zn)', '-', 4), prices: byHeight(PANEL_HEIGHTS, [1293, 1536, 1575, 1792, 1956, 2192]) },
    { title: '3D секция «Стандарт RAL»', group: 'Цинк + RAL', tone: 'green', specs: panelSpecs('цинк (Zn) + полимер RAL', '-', 4), prices: byHeight(PANEL_HEIGHTS, [1493, 1795, 1926, 2316, 2529, 2713]) },
  ],
}

const POST_HEIGHTS = ['1,7 м', '2,0 м', '2,2 м', '2,5 м', '2,7 м', '3,0 м']

const postSpecs = (coating) => [
  { label: 'Покрытие', value: coating, chip: '-' },
  { label: 'Сечение', value: '60×40×1,5 мм', chip: 'профиль 60×40×1,5 мм' },
  { label: 'Установка', value: 'в бетон', chip: 'в бетон' },
]

export const postsPriceGroup = {
  label: 'Столбы',
  rowsLabel: 'Высота столба',
  note: 'Столб 1,7 м — для панели 1,23 м (3 крепления); 2,0 м — для 1,53 м (3); 2,2 м — для 1,73 м (4); 2,5 м — для 2,03 м (4); 2,7 м — для 2,23 м (4); 3,0 м — для 2,40 м (4).',
  rows: POST_HEIGHTS,
  options: [
    { title: 'Столб в бетон «Эконом»', group: 'Цинк', tone: 'blue', specs: postSpecs('цинк (Zn)'), prices: byHeight(POST_HEIGHTS, [669, 787, 865, 983, 1062, 1144]) },
    { title: 'Столб в бетон «Стандарт»', group: 'Цинк + RAL', tone: 'green', specs: postSpecs('цинк (Zn) + полимер RAL'), prices: byHeight(POST_HEIGHTS, [874, 1029, 1130, 1285, 1387, 1542]) },
  ],
}

const WICKET_HEIGHTS = ['1,03 м', '1,23 м', '1,53 м', '1,75 м', '2,03 м', '2,23 м', '2,40 м']

export const wicketsPriceGroup = {
  label: 'Калитки',
  rowsLabel: 'Высота калитки',
  note: 'Ширина калитки — 1,00 м. Рёбер жёсткости: у калитки 1,03 м — 2; 1,23–1,75 м — 3; 2,03 и 2,23 м — 4; 2,40 м — 5.',
  rows: WICKET_HEIGHTS,
  options: [
    {
      title: 'Калитка для 3D забора',
      specs: [
        { label: 'Покрытие', value: 'цинк (Zn) + полимер RAL', chip: 'цинк + полимер RAL' },
        { label: 'Рама', value: 'труба 60×40×2 мм', chip: 'рама 60×40×2 мм' },
        { label: 'Заполнение', value: 'ячейка 200×50 мм, пруток 4 мм', chip: 'пруток 4 мм' },
        { label: 'Ширина', value: '1,00 м', chip: 'ширина 1 м' },
      ],
      prices: byHeight(WICKET_HEIGHTS, [11717, 12599, 13480, 14740, 16000, 17386, 20788]),
    },
  ],
}

const GATE_HEIGHTS = ['1,53 м', '1,73 м', '2,03 м', '2,23 м', '2,40 м']

const gateOption = (width, post, prices) => ({
  title: `Ворота шириной ${width}`,
  specs: [
    { label: 'Ширина', value: width, chip: '-' },
    { label: 'Труба для столба', value: post, chip: `столб ${post}` },
  ],
  prices: byHeight(GATE_HEIGHTS, prices),
})

export const gatesPriceGroup = {
  label: 'Ворота',
  rowsLabel: 'Высота ворот',
  note:
    'Распашные ворота: встроенный замок с ручкой, створки заполнены секциями, рама 60×40×2 мм, ячейка 200×50 мм, пруток 4 мм, покрытие цинк (Zn) + полимер RAL. ' +
    'Для ворот высотой 2,23 и 2,40 м шириной 4,0 м нужна труба для столба 100×100×3 мм.',
  rows: GATE_HEIGHTS,
  options: [
    gateOption('2,5 м', '80×80×3 мм', [33852, 35958, 36120, null, null]),
    gateOption('3,0 м', '80×80×3 мм', [35780, 36496, 37200, null, null]),
    gateOption('4,0 м', '80×80×3 мм', [38678, 41660, 42607, 50599, 57674]),
    gateOption('5,0 м', '100×100×3 мм', [43464, 47130, 49442, 56849, 64446]),
    gateOption('6,0 м', '100×100×3 мм', [46921, 52218, 54853, null, null]),
  ],
}

export const fastenersPriceGroup = {
  label: 'Крепёж',
  rowsLabel: 'Крепление',
  rows: ['Саморез + прижимная скоба'],
  options: [
    { title: 'Крепление секции, цинк', specs: [{ label: 'Покрытие', value: 'цинк (Zn)', chip: 'цинк' }], prices: { 'Саморез + прижимная скоба': 49 } },
    { title: 'Крепление секции, цинк + полимер', specs: [{ label: 'Покрытие', value: 'цинк (Zn) + полимер', chip: 'цинк + полимер' }], prices: { 'Саморез + прижимная скоба': 55 } },
  ],
}

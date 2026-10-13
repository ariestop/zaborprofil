/**
 * Прайсы материалов со старого сайта для блока «Прайс-лист с выбором» (null — такого варианта нет):
 * металлочерепица (https://zaborprofil.ru/fasad/metallocherepica/), металлосайдинг (https://zaborprofil.ru/metall-saiding/)
 * и пластиковые заглушки (https://zaborprofil.ru/zaglushki-plastik/). Цены перенесены из таблиц без изменений.
 */

const pricesFor = (rows, prices) => Object.fromEntries(rows.map((row, index) => [row, prices[index] ?? null]))

const coatingSpecs = (coating, metal, warranty) => [
  { label: 'Толщина покрытия', value: `${coating} мкм`, chip: `покрытие ${coating} мкм` },
  { label: 'Толщина металла', value: `${metal} мм`, chip: `металл ${metal} мм` },
  { label: 'Гарантия', value: `${warranty} лет`, chip: `гарантия ${warranty} лет` },
]

const ROOF_PROFILES = ['Ламонтерра', 'Ламонтерра X', 'Ламонтерра XL', 'Монтерроса', 'Трамонтана', 'Монтекристо']

/** Профили Ламонтерра, Ламонтерра X и XL на старом сайте в одной таблице — цены у них одинаковые. */
const roofOption = (title, specs, lamonterra, monterrosa, tramontana, montekristo) => ({
  title,
  specs,
  prices: pricesFor(ROOF_PROFILES, [lamonterra, lamonterra, lamonterra, monterrosa, tramontana, montekristo]),
})

export const metallocherepicaPriceGroup = {
  label: 'Металлочерепица',
  rowsLabel: 'Профиль',
  rows: ROOF_PROFILES,
  options: [
    roofOption('Полиэстер 0.40 мм', coatingSpecs('25', '0.40', '5'), 350, null, null, null),
    roofOption('Полиэстер 0.45 мм', coatingSpecs('25', '0.45', '5'), 413, null, null, null),
    roofOption('VikingMP 35 мкм', coatingSpecs('35', '0.45', '10'), 525, null, null, null),
    roofOption('Viking 30 мкм', coatingSpecs('30', '0.45', '10'), null, 570, 555, 555),
    roofOption('NormanMP', coatingSpecs('25', '0.50', '20'), 510, 565, 545, 520),
    roofOption('Valori', coatingSpecs('30', '0.50', '20'), null, 740, 710, 690),
    roofOption('Puretan', coatingSpecs('35', '0.50', '30'), 760, 865, 835, 810),
    roofOption('Cloudy', coatingSpecs('30', '0.50', '30'), 990, 1085, 1050, 1030),
    roofOption('VikingMP E 45 мкм', coatingSpecs('45', '0.50', '30'), 810, null, null, null),
    roofOption('VikingMP E 40 мкм', coatingSpecs('40', '0.50', '30'), null, 905, 880, 845),
    roofOption('Purman', coatingSpecs('50', '0.50', '40'), 885, 975, 950, 930),
    roofOption('Agneta', coatingSpecs('25/25', '0.50', '25'), 990, 1090, 1065, 1030),
  ],
}

const SIDING_PROFILES = ['Корабельная доска', 'L-брус', 'Woodstock']

const sidingOption = (title, metal, decor, prices) => ({
  title,
  specs: [
    { label: 'Толщина металла', value: metal, chip: `металл ${metal}` },
    ...(decor === '' ? [] : [{ label: 'Декор', value: decor, chip: decor }]),
  ],
  prices: pricesFor(SIDING_PROFILES, prices),
})

export const saidingPriceGroup = {
  label: 'Металлосайдинг',
  rowsLabel: 'Профиль',
  note: 'Woodstock выпускается только с покрытием ECOSTEEL — декор «морёный дуб», «сосна» или «кедр».',
  rows: SIDING_PROFILES,
  options: [
    sidingOption('Полиэстер', '0.40 мм', '', [680, 720, null]),
    sidingOption('Полиэстер', '0.45 мм', '', [880, 885, null]),
    sidingOption('VIKING матовый', '0.45 мм', '', [995, 1020, null]),
    sidingOption('VALORI матовый', '0.50 мм', '', [null, 1235, null]),
    sidingOption('ECOSTEEL матовый', '0.50+ мм', 'под дерево', [1540, 1560, 1645]),
  ],
}

const plug = (size, prices) => ({ title: size, prices })

const SQUARE_FORMS = ['Плоская', 'Внутренний домик', 'Наружный домик', 'Наружный колпак']

const squarePlug = (size, flat, innerHouse = null, outerHouse = null, outerCap = null) =>
  plug(size, pricesFor(SQUARE_FORMS, [flat, innerHouse, outerHouse, outerCap]))

export const squarePlugsPriceGroup = {
  label: 'Квадратные',
  rowsLabel: 'Форма',
  compact: true,
  rows: SQUARE_FORMS,
  options: [
    squarePlug('15×15 мм', 5),
    squarePlug('20×20 мм', 5),
    squarePlug('25×25 мм', 7),
    squarePlug('30×30 мм', 9),
    squarePlug('40×40 мм', 14),
    squarePlug('50×50 мм', 20),
    squarePlug('60×60 мм', 20, 30, 40, 35),
    squarePlug('80×80 мм', 30, 120),
    squarePlug('100×100 мм', 60, 150, null, 100),
    squarePlug('120×120 мм', 150),
    squarePlug('160×160 мм', 300),
  ],
}

const flatPlugs = (sizes) => sizes.map(([size, price]) => plug(size, { Плоская: price }))

export const rectangularPlugsPriceGroup = {
  label: 'Прямоугольные',
  rowsLabel: 'Форма',
  compact: true,
  rows: ['Плоская'],
  options: flatPlugs([
    ['20×10 мм', 7],
    ['20×15 мм', 8],
    ['30×20 мм', 9],
    ['40×20 мм', 9],
    ['40×25 мм', 10],
    ['50×25 мм', 12],
    ['60×30 мм', 21],
    ['60×40 мм', 20],
    ['80×40 мм', 22],
    ['80×60 мм', 25],
    ['100×50 мм', 70],
    ['150×100 мм', 280],
  ]),
}

export const roundPlugsPriceGroup = {
  label: 'Круглые',
  rowsLabel: 'Форма',
  compact: true,
  rows: ['Плоская'],
  options: flatPlugs(
    [
      [10, 5], [12, 5], [14, 5], [16, 5], [18, 5], [20, 5], [22, 6], [25, 6], [28, 7], [30, 7], [32, 7], [38, 10], [40, 13],
      [42, 14], [45, 18], [48, 21], [50, 22], [57, 23], [60, 25], [73, 28], [76, 28], [89, 60], [102, 120], [108, 130], [133, 150],
    ].map(([diameter, price]) => [`Ø ${diameter} мм`, price]),
  ),
}

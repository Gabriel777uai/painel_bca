function predict(x) {
  y = x * 5 - 3;
  return y;
}

console.log("Result prevision: " + predict(4));

function classifier(x) {
  y = [1, 2, 3, 4, 6, 7, 8, 9];

  const divider = Math.floor(y.length / 2);
  const newY = y.slice(0, divider);
  const finalY = y.slice(divider, divider * 2);
  const media = (newY[newY.length - 1] + finalY[0]) / 2;

  if (x >= media) {
    return 1;
  } else {
    return 0;
  }
}
console.log("Classificação: " + classifier(4));

function plot(x) {
  const y = x * -2 + 4;
  return y;
}
console.log("Regressão: " + plot(3));

function peso(w = 0.2, eta = 0.1, erro = 1, x = 1) {
  const r = w + eta * erro * x;
  return r;
}

console.log(peso());

function neuronio() {
  const x1 = 1;
  const x2 = 1;

  const w1 = 0.4;
  const w2 = 0.6;
  const b = -0.5;

  const z = x1 * w1 + x2 * w2 + b;

  if (z >= 0) y = 1;
  else y = 0;

  return y;
}
console.log(neuronio());

function porta_or(x1, x2) {
  const w1 = 1;
  const w2 = 1;
  const b = -0.5;

  const z = x1 * w1 + x2 * w2 + b;

  return z >= 0 ? 1 : 0;
}

console.log(porta_or(0, 1));
console.log(porta_or(1, 0));
console.log(porta_or(0, 0));

function update_peso() {
  const w = 0.2;
  const eta = 0.1;
  const erro = 1;
  const x = 1;

  const r = w + eta * erro * x;

  return r;
}

console.log(update_peso());

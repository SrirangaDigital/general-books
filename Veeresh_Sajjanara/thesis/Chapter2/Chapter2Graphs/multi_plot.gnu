set terminal postscript portrait
set size 1.0, 0.5

set output "multi_plot.ps"
set xlabel "X values"
set ylabel "Y values"
set xrange [0:800]
set yrange [0:40]
set nogrid
set key
set linestyle 1 lt 3 lw 1
set linestyle 2 lt 1 lw 1
plot 'input1.dat'  t 'Input1' w l ls 1, \
     'input2.dat'  t 'Input2' w l ls 2


set terminal postscript portrait
set size 1.0, 0.5

set output "single_plot.ps"
set xlabel "X values"
set ylabel "Y values"
set xrange [0:800]
set yrange [0:40]
set nogrid
set key
set linestyle 2 lt 1 lw 1
plot 'input1.dat'  t 'Single plot'  w l ls 2

